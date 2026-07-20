<?php

namespace VMP\Support {
    defined('ABSPATH') || exit;

    use VMP\Core\Application;
    use VMP\Core\Container;
    use VMP\Support\Config;
    use VMP\Repositories\SubscriptionRepository;

    /**
     * الحصول على قيمة إعداد من ملفات الإعدادات
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config;
        if ($config === null) {
            $config = Config::getInstance(VMP_PLUGIN_DIR . 'app/Config');
        }
        return $config->get($key, $default);
    }

    /**
     * الحصول على كائن التطبيق
     */
    function app(): ?Application
    {
        $container = Container::getInstance();
        $instance = $container->make('app');
        return $instance instanceof Application ? $instance : null;
    }

    // ─────────────────────────────────────────────────────────────
    // دوال مساعدة للبائع (Vendor Helpers) - تم دمجها من الملف الثاني
    // ─────────────────────────────────────────────────────────────

    /**
     * الحصول على معرف البائع للمستخدم الحالي
     */
    function get_current_vendor_id(): int
    {
        global $wpdb;
        $user_id = get_current_user_id();
        if (!$user_id) {
            return 0;
        }

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        return (int) ($id ?? 0);
    }

    /**
     * الحصول على بيانات البائع بواسطة معرف البائع
     */
    function get_vendor(int $vendor_id): ?object
    {
        global $wpdb;
        if (!$vendor_id) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vmp_vendors WHERE id = %d LIMIT 1",
            $vendor_id
        )) ?: null;
    }

    /**
     * هل المستخدم الحالي بائع مسجّل ومعتمد؟
     */
    function is_vendor(int $user_id = 0): bool
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        if (!$user_id) {
            return false;
        }

        $user = wp_get_current_user();
        if (!in_array('vmp_vendor', (array) $user->roles)) {
            return false;
        }

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        return $status === 'approved';
    }

    /**
     * إعادة توجيه المستخدم إذا لم يكن بائعاً مسجّلاً، أو إذا كان حسابه معلقاً
     * استخدم هذا في أعلى قوالب لوحة تحكم البائع
     */
    function require_vendor(bool $require_approved = false): void
    {
        if (!is_user_logged_in()) {
            $current_url = home_url($_SERVER['REQUEST_URI'] ?? '/');
            wp_redirect(wp_login_url($current_url));
            exit;
        }

        $vendor_id = get_current_vendor_id();
        if (!$vendor_id) {
            $settings = get_option('vmp_settings', []);
            $register_page_id = !empty($settings['display']['register_page']) ? (int) $settings['display']['register_page'] : 0;
            $register_page = $register_page_id && get_post($register_page_id)
                ? get_permalink($register_page_id)
                : home_url('/vendor-register/');
            wp_redirect($register_page);
            exit;
        }

        if ($require_approved) {
            $vendor = get_vendor($vendor_id);
            if ($vendor && $vendor->status !== 'approved') {
                // لا تعيد التوجيه — فقط نعرض تنبيهاً داخل القالب
            }
        }
    }

    /**
     * الحصول على رابط لوحة تحكم البائع
     */
    function dashboard_url(string $page = ''): string
    {
        $settings = get_option('vmp_settings', []);
        $dashboard_page_id = !empty($settings['display']['dashboard_page']) ? (int) $settings['display']['dashboard_page'] : 0;
        $dashboard_page = $dashboard_page_id && get_post($dashboard_page_id)
            ? get_permalink($dashboard_page_id)
            : home_url('/vendor-dashboard/');

        if ($page) {
            return add_query_arg('vmp_page', $page, $dashboard_page);
        }
        return $dashboard_page;
    }

    /**
     * الحصول على معرف البائع عبر معرف المنتج
     */
    function get_product_vendor_id(int $product_id): int
    {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT vendor_id FROM {$wpdb->prefix}vmp_vendor_products WHERE product_id = %d LIMIT 1",
            $product_id
        ));
        return (int) ($id ?? 0);
    }

    /**
     * تنسيق المبلغ المالي بعملة الموقع
     */
    function price(float $amount): string
    {
        return function_exists('wc_price') ? wc_price($amount) : number_format($amount, 2) . ' ر.س';
    }

    /**
     * هل يمكن للمستخدم الحالي (البائع) إضافة منتج جديد؟
     */
    function can_add_product(int $vendor_id = 0): bool
    {
        if (!$vendor_id) {
            $vendor_id = get_current_vendor_id();
        }
        if (!$vendor_id) {
            return false;
        }

        try {
            $sub_repo = Container::getInstance()->make(SubscriptionRepository::class);
            return $sub_repo->canAddProduct($vendor_id);
        } catch (\Exception $e) {
            // في حال فشل الحصول على المستودع، نستخدم fallback
            global $wpdb;
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}vmp_vendor_products WHERE vendor_id = %d",
                $vendor_id
            ));
            return $count < 10;
        }
    }
}

// ─────────────────────────────────────────────────────────────
// تسجيل الدوال في النطاق العام (Global Namespace)
// ─────────────────────────────────────────────────────────────
namespace {
    if (!function_exists('config')) {
        function config(string $key, mixed $default = null): mixed
        {
            return \VMP\Support\config($key, $default);
        }
    }

    if (!function_exists('app')) {
        function app(): ?\VMP\Core\Application
        {
            return \VMP\Support\app();
        }
    }

    // ── دوال البائع المساعدة (Global Wrappers) ──
    if (!function_exists('vmp_get_current_vendor_id')) {
        function vmp_get_current_vendor_id(): int
        {
            return \VMP\Support\get_current_vendor_id();
        }
    }

    if (!function_exists('vmp_get_vendor')) {
        function vmp_get_vendor(int $vendor_id): ?object
        {
            return \VMP\Support\get_vendor($vendor_id);
        }
    }

    if (!function_exists('vmp_is_vendor')) {
        function vmp_is_vendor(int $user_id = 0): bool
        {
            return \VMP\Support\is_vendor($user_id);
        }
    }

    if (!function_exists('vmp_require_vendor')) {
        function vmp_require_vendor(bool $require_approved = false): void
        {
            \VMP\Support\require_vendor($require_approved);
        }
    }

    if (!function_exists('vmp_dashboard_url')) {
        function vmp_dashboard_url(string $page = ''): string
        {
            return \VMP\Support\dashboard_url($page);
        }
    }

    if (!function_exists('vmp_get_product_vendor_id')) {
        function vmp_get_product_vendor_id(int $product_id): int
        {
            return \VMP\Support\get_product_vendor_id($product_id);
        }
    }

    if (!function_exists('vmp_price')) {
        function vmp_price(float $amount): string
        {
            return \VMP\Support\price($amount);
        }
    }

    if (!function_exists('vmp_can_add_product')) {
        function vmp_can_add_product(int $vendor_id = 0): bool
        {
            return \VMP\Support\can_add_product($vendor_id);
        }
    }
}