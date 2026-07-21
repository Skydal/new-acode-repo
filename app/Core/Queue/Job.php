<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

use JsonException;

/**
 * Class Job
 *
 * نموذج يمثل كينونة الوظيفة داخل قاعدة البيانات
 */
class Job
{
    public function __construct(
        public readonly int $id,
        public readonly string $jobClass,
        public readonly array $payload,
        public readonly string $status = 'pending',
        public readonly int $attempts = 0,
        public readonly ?string $errorMessage = null,
        public readonly ?string $lockedAt = null,
        public readonly ?string $createdAt = null
    ) {}

    /**
     * بناء نموذج من كائن صف قاعدة البيانات
     *
     * @throws JsonException إذا كان الـ payload غير صالح JSON
     */
    public static function fromDbRow(object $row): self
    {
        $payload = [];
        if (!empty($row->payload)) {
            // Use strict JSON decoding to detect corrupt payloads
            $decoded = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return new self(
            id: (int) $row->id,
            jobClass: $row->job_class,
            payload: $payload,
            status: $row->status,
            attempts: (int) $row->attempts,
            errorMessage: $row->error_message,
            lockedAt: $row->locked_at,
            createdAt: $row->created_at
        );
    }
}
