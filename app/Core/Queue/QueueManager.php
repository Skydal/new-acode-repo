<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Core\Logger;

/**
 * Class QueueManager
 *
 * يدير طوابير العمل في الخلفية
 */
class QueueManager
{
    private string $table;

    public function __construct(
        private Container $container,
        private Logger $logger,
        private \wpdb $db
    ) {
        $this->table = $this->db->prefix . 'vmp_jobs';
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
        $jobClass = $job->jobClass;

        if (!class_exists($jobClass)) {
            $this->markAsFailed($job, sprintf('كلاس الوظيفة %s غير موجود.', $jobClass));
            return false;
        }

        try {
            // استخدام حاوية الـ DI لبناء كائن الوظيفة
            // الكلاس نفسه قد يستخدم دالة static fromPayload
            if (method_exists($jobClass, 'fromPayload')) {
                $jobInstance = call_user_func([$jobClass, 'fromPayload'], $job->payload);
            } else {
                $jobInstance = $this->container->make($jobClass);
            }

            if (!$jobInstance instanceof JobInterface) {
                throw new \RuntimeException(sprintf('الوظيفة %s يجب أن تطبق JobInterface.', $jobClass));
            }

            // تنفيذ الوظيفة
            $jobInstance->handle();

            // مسح الوظيفة بعد النجاح للحفاظ على حجم قاعدة البيانات، أو تحديث حالتها لـ completed
            // سنقوم بتحديث الحالة إلى completed لتوفير سجل للعمليات
            $this->markAsCompleted($job);
            return true;

        } catch (\Throwable $e) {
            $this->logger->error('حدث خطأ أثناء معالجة الوظيفة #' . $job->id, [
                'job_class' => $jobClass,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            $this->markAsFailed($job, $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * حجز وظائف معينة لتشغيلها بشكل آمن لمنع التكرار (Race Condition)
     *
     * @param int $limit
     * @return Job[]
     */
    private function claimJobs(int $limit): array
    {
        // 1. تحديد المعرفات للوظائف الجاهزة
        $sql = "SELECT id FROM {$this->table}
                WHERE status = 'pending'
                AND (locked_at IS NULL OR locked_at < DATE_SUB(%s, INTERVAL 10 MINUTE))
                ORDER BY created_at ASC
                LIMIT %d";

        $now = current_time('mysql');
        $jobIds = $this->db->get_col($this->db->prepare($sql, $now, $limit));

        if (empty($jobIds)) {
            return [];
        }

        // تحويل المعرفات لقائمة مفصولة بفاصلة
        $idsCsv = implode(',', array_map('intval', $jobIds));

        // 2. قفل الوظائف
        $lockSql = "UPDATE {$this->table}
                    SET status = 'processing', locked_at = %s, attempts = attempts + 1
                    WHERE id IN ($idsCsv)";
        
        $this->db->query($this->db->prepare($lockSql, $now));

        // 3. استدعاء بيانات الوظائف المقفلة
        $fetchSql = "SELECT * FROM {$this->table} WHERE id IN ($idsCsv)";
        $rows = $this->db->get_results($fetchSql);

        $jobs = [];
        foreach ($rows as $row) {
            $jobs[] = Job::fromDbRow($row);
        }

        return $jobs;
    }

    /**
     * وسم الوظيفة كمكتملة
     */
    private function markAsCompleted(Job $job): void
    {
        // لحفظ المساحة نقوم بمسح الوظيفة الناجحة
        $this->db->delete($this->table, ['id' => $job->id]);
    }

    /**
     * وسم الوظيفة كفاشلة وإعادتها للطابور أو وسمها كفشل نهائي
     */
    private function markAsFailed(Job $job, string $error): void
    {
        $maxAttempts = 3;
        $status = $job->attempts >= $maxAttempts ? 'failed' : 'pending';

        $this->db->update(
            $this->table,
            [
                'status'        => $status,
                'error_message' => $error,
                'locked_at'     => null,
            ],
            ['id' => $job->id]
        );
    }
}
