<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Core\Logger;
use JsonException;
use VMP\Core\Queue\Exceptions\RetryLaterException;

/**
 * Class QueueManager
 *
 * يدير طوابير العمل في الخلفية
 */
class QueueManager
{
    private string $table;

    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_SECONDS = [60, 300, 900]; // 1m, 5m, 15m

    public function __construct(
        private Container $container,
        private Logger $logger,
        private \wpdb $db
    ) {
        $this->table = $this->db->prefix . 'vmp_jobs';

        // Schedule cleanup via Action Scheduler if available
        if (function_exists('as_next_scheduled')) {
            if (!as_next_scheduled('vmp_cleanup_jobs_daily')) {
                // schedule daily
                if (function_exists('as_schedule_recurring_action')) {
                    as_schedule_recurring_action(time(), DAY_IN_SECONDS, 'vmp_cleanup_jobs_daily');
                }
            }
            // Hook handler is registered elsewhere (or we can add it here)
            add_action('vmp_cleanup_jobs_daily', function () {
                try {
                    $job = new Jobs\CleanupOldJobs();
                    $job->handle();
                } catch (\Throwable $e) {
                    $this->logger->error('Cleanup job failed', ['error' => $e->getMessage()]);
                }
            });
        }
    }

    /**
     * دفع وظيفة جديدة إلى الطابور
     *
     * @param string $jobClass اسم الكلاس الكامل للوظيفة
     * @param array  $payload  البيانات الممررة للوظيفة
     * @return int معرف الوظيفة المضافة
     */
    public function push(string $jobClass, array $payload = []): int
    {
        $inserted = $this->db->insert($this->table, [
            'job_class'  => sanitize_text_field($jobClass),
            'payload'    => wp_json_encode($payload),
            'status'     => 'pending',
            'attempts'   => 0,
            'created_at' => current_time('mysql'),
        ]);

        if (!$inserted) {
            $this->logger->error('فشل في إدخال الوظيفة إلى الطابور', [
                'job_class' => $jobClass,
                'payload'   => $payload,
            ]);
            return 0;
        }

        return (int) $this->db->insert_id;
    }

    /**
     * جلب وظائف جاهزة ومعالجتها
     *
     * @param int $limit أقصى عدد من الوظائف المراد معالجتها في الدفعة الواحدة
     * @return int عدد الوظائف التي تم معالجتها بنجاح
     */
    public function run(int $limit = 5): int
    {
        $jobs = $this->claimJobs($limit);
        if (empty($jobs)) {
            return 0;
        }

        $processedCount = 0;

        foreach ($jobs as $job) {
            if ($this->process($job)) {
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * معالجة وظيفة محددة
     *
     * @param Job $job
     * @return bool نجاح أو فشل المعالجة
     */
    public function process(Job $job): bool
    {
        $start = microtime(true);
        $jobClass = $job->jobClass;

        if (!class_exists($jobClass)) {
            $this->markAsFailedPermanent($job, sprintf('كلاس الوظيفة %s غير موجود.', $jobClass));
            return false;
        }

        try {
            // استخدام حاوية الـ DI لبناء كائن الوظيفة
            // الكلاس نفسه قد يستخدم دالة static fromPayload
            if (method_exists($jobClass, 'fromPayload')) {
                $jobInstance = call_user_func([$jobClass, 'fromPayload'], $job->payload);
            } else {
                try {
                    $jobInstance = $this->container->make($jobClass);
                } catch (\Throwable $e) {
                    // Fallback: attempt direct instantiation if container cannot
                    $jobInstance = new $jobClass($job->payload);
                }
            }

            if (!$jobInstance instanceof JobInterface) {
                throw new \RuntimeException(sprintf('الوظيفة %s يجب أن تطبق JobInterface.', $jobClass));
            }

            // تنفيذ الوظيفة
            $jobInstance->handle();

            // وسم الوظيفة كمكتملة (لا نحذفها فوراً)
            $this->markAsCompleted($job);
            return true;

        } catch (RetryLaterException $e) {
            // Transient error — schedule retry with optional override delay
            $this->scheduleRetry($job, 'Transient: ' . $e->getMessage());
            return false;

        } catch (JsonException $je) {
            // Payload is corrupted (should normally be handled at claim time)
            $this->markAsFailedPermanent($job, 'Invalid payload JSON: ' . $je->getMessage());
            return false;

        } catch (\Throwable $e) {
            // Non-retryable error -> mark failed (may be scheduled for retry depending on attempts)
            $trace = $this->maskSecrets($e->getTraceAsString());
            $this->logger->error('حدث خطأ أثناء معالجة الوظيفة #' . $job->id, [
                'job_class' => $jobClass,
                'error'     => $e->getMessage(),
                'trace'     => $trace,
            ]);

            $this->scheduleRetry($job, $e->getMessage());
            return false;

        } finally {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $this->logger->info('Job processed', ['job_id' => $job->id, 'job_class' => $job->jobClass, 'attempt' => $job->attempts, 'duration_ms' => $durationMs]);
        }
    }

    /**
     * Claim N jobs atomically (SELECT ids then UPDATE WHERE id IN (...) AND status='pending')
     * This approach keeps compatibility with MariaDB and shared hosts.
     *
     * @param int $limit
     * @return Job[]
     */
    private function claimJobs(int $limit): array
    {
        $now = current_time('mysql');

        try {
            $this->db->query('START TRANSACTION');

            // 1) select candidate ids
            $selectSql = "SELECT id FROM {$this->table}
                WHERE status = 'pending'
                AND (locked_at IS NULL OR locked_at <= %s)
                ORDER BY created_at ASC
                LIMIT %d";

            $ids = $this->db->get_col($this->db->prepare($selectSql, $now, $limit));

            if (empty($ids)) {
                $this->db->query('COMMIT');
                return [];
            }

            $idsCsv = implode(',', array_map('intval', $ids));

            // 2) attempt to claim them — update only rows still pending (prevents race)
            $lockSql = "UPDATE {$this->table}
                SET status = 'processing', locked_at = %s, attempts = attempts + 1
                WHERE id IN ($idsCsv) AND status = 'pending' AND (locked_at IS NULL OR locked_at <= %s)";

            $this->db->query($this->db->prepare($lockSql, $now, $now));

            $affected = $this->db->rows_affected;

            if ($affected === 0) {
                // nobody claimed — commit and return
                $this->db->query('COMMIT');
                return [];
            }

            // 3) fetch the rows that were actually locked (status = processing and locked_at == $now)
            $fetchSql = $this->db->prepare("SELECT * FROM {$this->table} WHERE status = 'processing' AND locked_at = %s ORDER BY created_at ASC LIMIT %d", $now, $limit);
            $rows = $this->db->get_results($fetchSql);

            $this->db->query('COMMIT');

        } catch (\Throwable $e) {
            try { $this->db->query('ROLLBACK'); } catch (\Throwable $_) {}
            $this->logger->error('Failed to claim jobs transactionally', ['error' => $e->getMessage()]);
            return [];
        }

        $jobs = [];
        foreach ($rows as $row) {
            try {
                $job = Job::fromDbRow($row);
            } catch (JsonException $je) {
                // payload corrupted -> mark failed permanently
                $this->db->update($this->table, [
                    'status' => 'failed',
                    'error_message' => 'Invalid JSON payload: ' . $je->getMessage(),
                    'locked_at' => null,
                ], ['id' => (int) $row->id]);

                $this->logger->error('Job payload JSON invalid, marking failed', ['job_id' => (int) $row->id, 'error' => $je->getMessage()]);
                continue;
            }

            // DB already incremented attempts; Job::fromDbRow will reflect the current attempts
            $jobs[] = $job;
        }

        return $jobs;
    }

    /**
     * وسم الوظيفة كمكتملة (نحتفظ بها لسجلّ مؤقت ثم تحذف لاحقاً عبر Cleanup Job)
     */
    private function markAsCompleted(Job $job): void
    {
        $this->db->update(
            $this->table,
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'locked_at' => null,
            ],
            ['id' => $job->id]
        );

        $this->logger->info('Job completed', ['job_id' => $job->id]);
    }

    /**
     * وسم الفشل الدائم (لا إعادة)
     */
    private function markAsFailedPermanent(Job $job, string $error): void
    {
        $this->db->update(
            $this->table,
            [
                'status' => 'failed',
                'error_message' => $error,
                'locked_at' => null,
            ],
            ['id' => $job->id]
        );

        $this->logger->error('Job failed permanently', ['job_id' => $job->id, 'error' => $error]);
    }

    /**
     * جدولة إعادة المحاولة (transient errors)
     */
    private function scheduleRetry(Job $job, string $error, ?int $overrideDelaySeconds = null): void
    {
        // If attempts already reached max, mark as permanent failed
        if ($job->attempts >= self::MAX_ATTEMPTS) {
            $this->markAsFailedPermanent($job, $error);
            return;
        }

        if ($overrideDelaySeconds !== null) {
            $delay = $overrideDelaySeconds;
        } else {
            $attemptIndex = max(0, min(count(self::BACKOFF_SECONDS) - 1, $job->attempts - 1));
            $delay = self::BACKOFF_SECONDS[$attemptIndex] ?? self::BACKOFF_SECONDS[0];
        }

        $availableAt = date('Y-m-d H:i:s', current_time('timestamp') + $delay);

        $this->db->update(
            $this->table,
            [
                'status' => 'pending',
                'error_message' => $error,
                'locked_at' => $availableAt,
            ],
            ['id' => $job->id]
        );

        $this->logger->warning('Job scheduled for retry', ['job_id' => $job->id, 'next_try_at' => $availableAt, 'attempts' => $job->attempts, 'error' => $error]);
    }

    /**
     * دالة مساعدة لتخفي الأسرار من traces قبل اللوج
     */
    private function maskSecrets(string $trace): string
    {
        // Mask common header keys and tokens
        $patterns = [
            '/(Authorization:\s*)([^\\s]+)/i',
            '/(api[_-]?key["\'\s=>:]*)([A-Za-z0-9\-_=]+)/i',
            '/(token["\'\s=>:]*)([A-Za-z0-9\-_=]+)/i',
            '/(secret["\'\s=>:]*)([A-Za-z0-9\-_=]+)/i',
        ];
        $trace = preg_replace($patterns, ['$1[REDACTED]', '$1[REDACTED]', '$1[REDACTED]', '$1[REDACTED]'], $trace);

        // Generic long hex strings
        $trace = preg_replace('/\b[0-9a-f]{32,}\b/i', '[REDACTED]', $trace);

        return $trace;
    }
}
