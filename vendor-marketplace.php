<?php
/**
 * Plugin Name: Vendor Marketplace
 * Description: Multi-vendor marketplace plugin for WooCommerce with subscriptions, AI, commissions, and WhatsApp.
 * Version: 1.0.0
 * Author: Max Arafat
 * Text Domain: vmp
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// ─── Define Constants ───────────────────────────────────────────────────────
if (!defined('VMP_VERSION')) {
    define('VMP_VERSION', '1.0.0');
}
if (!defined('VMP_PLUGIN_FILE')) {
    define('VMP_PLUGIN_FILE', __FILE__);
}
if (!defined('VMP_PLUGIN_DIR')) {
    define('VMP_PLUGIN_DIR', plugin_dir_path(__FILE__) . DIRECTORY_SEPARATOR);
}
if (!defined('VMP_PLUGIN_URL')) {
    define('VMP_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('VMP_PLUGIN_BASENAME')) {
    define('VMP_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// ─── Autoloader ──────────────────────────────────────────────────────────
$autoload = VMP_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// ─── Helpers ─────────────────────────────────────────────────────────────
$helpers = VMP_PLUGIN_DIR . 'app/Support/helpers.php';
if (file_exists($helpers)) {
    require_once $helpers;
}

// ─── Legacy Compatibility ───────────────────────────────────────────────────
$legacy = VMP_PLUGIN_DIR . 'app/Support/LegacyCompat.php';
if (file_exists($legacy)) {
    require_once $legacy;
}

// ─── Bootstrap Application ──────────────────────────────────────────────────
$bootstrap = VMP_PLUGIN_DIR . 'app/bootstrap.php';
if (!file_exists($bootstrap)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="error"><p><strong>Vendor Marketplace:</strong> Bootstrap file missing.</p></div>';
    });
    return;
}

/** @var \VMP\Core\Application $app */
$app = require_once $bootstrap;

if (!$app instanceof \VMP\Core\Application) {
    add_action('admin_notices', static function (): void {
        echo '<div class="error"><p><strong>Vendor Marketplace:</strong> Application bootstrap failed.</p></div>';
    });
    return;
}

// Debug: log bootstrap success when WP_DEBUG is enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[VMP] bootstrap loaded, Application instance: ' . (is_object($app) ? get_class($app) : gettype($app)));
}

// ─── Register & Boot ───────────────────────────────────────────────────────
add_action('plugins_loaded', static function () use ($app): void {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[VMP] plugins_loaded fired, starting app->register and app->boot');
    }
    try {
        $app->register();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP] app->register completed');
        }
        $app->boot();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP] app->boot completed');
        }
    } catch (\Throwable $e) {
        error_log('[VMP] Exception during plugin boot: ' . $e->getMessage());
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($e->getTraceAsString());
        }
    }
}, 20);
