<?php
namespace VMP\Core\Queue\Migrations;

defined('ABSPATH') || exit;

class V1_AddJobColumns
{
    public static function up(\wpdb $wpdb): void
    {
        $table = $wpdb->prefix . 'vmp_jobs';

        // Helper to check column existence
        $columnExists = function(string $col) use ($wpdb, $table): bool {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                $table,
                $col
            ));
            return (bool) $count;
        };

        if (!$columnExists('attempts')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `attempts` INT NOT NULL DEFAULT 0");
        }

        if (!$columnExists('locked_at')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `locked_at` DATETIME NULL DEFAULT NULL");
        }

        if (!$columnExists('completed_at')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `completed_at` DATETIME NULL DEFAULT NULL");
        }

        if (!$columnExists('error_message')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `error_message` TEXT NULL DEFAULT NULL");
        }

        // Create indexes if not exists (note: MySQL doesn't support IF NOT EXISTS for create index in older versions)
        // We attempt to create and ignore errors where index exists.
        try {
            $wpdb->query("CREATE INDEX idx_status_locked ON `{$table}` (`status`, `locked_at`)");
        } catch (\Throwable $_) {}

        try {
            $wpdb->query("CREATE INDEX idx_created ON `{$table}` (`created_at`)");
        } catch (\Throwable $_) {}

        try {
            $wpdb->query("CREATE INDEX idx_completed ON `{$table}` (`completed_at`)");
        } catch (\Throwable $_) {}

        try {
            $wpdb->query("CREATE INDEX idx_status_attempts ON `{$table}` (`status`, `attempts`)");
        } catch (\Throwable $_) {}
    }
}
