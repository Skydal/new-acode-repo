<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * Class Migration
 *
 * Handles database migrations for the plugin.
 *
 * @package vendor-marketplace
 */
class Migration
{
    /**
     * Run all pending migrations.
     *
     * @return void
     */
    public static function run(): void
    {
        // Migration logic handled by Install class
    }

    /**
     * Create the AI provider secrets table.
     */
    public static function createSecretsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vmp_ai_provider_secrets';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `provider` varchar(50) NOT NULL,
            `ciphertext` longtext NOT NULL,
            `iv` varchar(255) NOT NULL,
            `tag` varchar(255) NOT NULL,
            `algorithm` varchar(20) DEFAULT 'aes-256-gcm',
            `key_version` int(11) DEFAULT 1,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `provider` (`provider`)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Create the AI job locks table.
     */
    public static function createJobLocksTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vmp_ai_job_locks';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `lock_id` varchar(100) NOT NULL,
            `acquired_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `expires_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `lock_id` (`lock_id`)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Check if a table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool
     */
    public static function tableExists(string $table): bool
    {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . $table
        ));
        return !empty($result);
    }

    /**
     * Check if a column exists in a table.
     *
     * @param string $table Table name without prefix.
     * @param string $column Column name.
     * @return bool
     */
    public static function columnExists(string $table, string $column): bool
    {
        global $wpdb;
        $result = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$wpdb->prefix}%s` LIKE %s",
            $table,
            $column
        ));
        return !empty($result);
    }
}
