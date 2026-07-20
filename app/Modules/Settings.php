<?php
namespace VMP\Modules;

use VMP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وحدة الإعدادات – تدير إعدادات الإضافة من لوحة المشرف
 * تدعم الإعدادات المتداخلة، وتستخدم نظام config() المركزي
 */
class Settings extends AbstractModule
{
    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * تهيئة الوحدة وتسجيل الإجراءات
     */
    public function init(): void
    {
        // تسجيل إجراء حفظ الإعدادات
        add_action('wp_ajax_vmp_admin_save_settings', [$this, 'save_settings']);

        // تسجيل إجراء جلب الإعدادات (للتطوير)
        add_action('wp_ajax_vmp_admin_get_settings', [$this, 'get_settings']);

        // ✅ إضافة أكشنات الإشعارات
        add_action('wp_ajax_vmp_mark_notice_read', [$this, 'mark_notice_read']);
        add_action('wp_ajax_vmp_mark_all_notices_read', [$this, 'mark_all_notices_read']);
    }

    /**
     * حفظ الإعدادات (AJAX)
     * يتم استقبال الإعدادات كمصفوفة متداخلة وتخزينها في قاعدة البيانات
     */
    public function save_settings(): void
    {
        try {
            // ── 1. التحقق من الأمان ──
            if (!check_ajax_referer('vmp_admin_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => __('طلب غير مصرح به (nonce غير صحيح).', 'vmp')]);
            }

            if (!current_user_can('vmp_manage_settings')) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية لتعديل الإعدادات.', 'vmp')]);
            }

            // ── 2. جلب الإعدادات من الطلب ──
            $settings = isset($_POST['vmp_settings']) && is_array($_POST['vmp_settings'])
                ? $_POST['vmp_settings']
                : [];

            if (empty($settings)) {
                wp_send_json_error(['message' => __('لم يتم إرسال أي إعدادات.', 'vmp')]);
            }

            // ── 3. تنقية الإعدادات بشكل متكرر ──
            $sanitized_settings = $this->sanitizeSettings($settings);

            // ── 4. دمج الإعدادات الجديدة مع القديمة (للحفاظ على القيم غير المرسلة) ──
            $old_settings = get_option('vmp_settings', []);
            $merged_settings = $this->mergeSettings($old_settings, $sanitized_settings);

            // ── 5. حفظ الإعدادات ──
            $updated = update_option('vmp_settings', $merged_settings);

            if ($updated === false) {
                // قد تكون القيم مطابقة تماماً (لا تغيير)
                wp_send_json_success(['message' => __('الإعدادات محفوظة بالفعل.', 'vmp')]);
            }

            // ── 6. تسجيل الحدث ──
            $this->make('event_manager')->trigger('vmp_settings_saved', $merged_settings);

            // ── 7. إعادة بناء التخزين المؤقت إذا لزم الأمر ──
            wp_cache_delete('vmp_settings', 'options');

            wp_send_json_success(['message' => __('تم حفظ الإعدادات بنجاح.', 'vmp')]);

        } catch (\Exception $e) {
            $this->make('logger')->error('فشل حفظ الإعدادات', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * جلب الإعدادات الحالية (AJAX)
     * يُستخدم في واجهة المشرف لتحميل الإعدادات
     */
    public function get_settings(): void
    {
        try {
            check_ajax_referer('vmp_admin_nonce', 'nonce');

            if (!current_user_can('vmp_manage_settings')) {
                wp_send_json_error(['message' => __('غير مصرح لك.', 'vmp')]);
            }

            $settings = get_option('vmp_settings', []);

            wp_send_json_success([
                'settings' => $settings,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * تنقية الإعدادات بشكل متكرر (يدعم المصفوفات المتداخلة)
     *
     * @param array $settings مصفوفة الإعدادات
     * @param string $context سياق التنقية (للمساعدة في التصحيح)
     * @return array مصفوفة الإعدادات المنقاة
     */
    private function sanitizeSettings(array $settings, string $context = ''): array
    {
        $result = [];

        foreach ($settings as $key => $value) {
            $sanitized_key = sanitize_key($key);

            if (is_array($value)) {
                // تنقية المصفوفات المتداخلة
                $result[$sanitized_key] = $this->sanitizeSettings($value, $context . '.' . $sanitized_key);
            } elseif (is_string($value)) {
                // تنقية النصوص
                $result[$sanitized_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                // تنقية الأرقام
                $result[$sanitized_key] = (float) $value;
            } elseif (is_bool($value)) {
                // القيم المنطقية
                $result[$sanitized_key] = (bool) $value;
            } else {
                // القيم الأخرى (مثل null)
                $result[$sanitized_key] = $value;
            }
        }

        return $result;
    }

    /**
     * دمج الإعدادات الجديدة مع القديمة للحفاظ على القيم غير المرسلة
     *
     * @param array $old الإعدادات القديمة
     * @param array $new الإعدادات الجديدة
     * @return array الإعدادات المدمجة
     */
    private function mergeSettings(array $old, array $new): array
    {
        $merged = $old;

        foreach ($new as $key => $value) {
            if (is_array($value) && isset($old[$key]) && is_array($old[$key])) {
                // دمج المصفوفات المتداخلة
                $merged[$key] = $this->mergeSettings($old[$key], $value);
            } else {
                // استبدال القيم الجديدة
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * الحصول على إعداد معين (دالة مساعدة للاستخدام الداخلي)
     *
     * @param string $key مفتاح الإعداد (مثل 'display.dashboard_page')
     * @param mixed $default القيمة الافتراضية إذا لم يكن موجوداً
     * @return mixed قيمة الإعداد
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = get_option('vmp_settings', []);
        $keys = explode('.', $key);
        $value = $settings;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * تحديث إعداد معين (دالة مساعدة للاستخدام الداخلي)
     *
     * @param string $key مفتاح الإعداد (مثل 'display.dashboard_page')
     * @param mixed $value القيمة الجديدة
     * @return bool نجاح أو فشل العملية
     */
    public function setSetting(string $key, $value): bool
    {
        $settings = get_option('vmp_settings', []);
        $keys = explode('.', $key);
        $target = &$settings;

        foreach ($keys as $k) {
            if (!isset($target[$k]) || !is_array($target[$k])) {
                $target[$k] = [];
            }
            $target = &$target[$k];
        }

        $target = $value;

        return update_option('vmp_settings', $settings);
    }

    /**
     * ✅ تحديد إشعار كمقروء (AJAX)
     */
    public function mark_notice_read(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('غير مصرح', 'vmp')]);
        }

        $vendor_id = vmp_get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $notice_id = sanitize_text_field($_POST['notice_id'] ?? '');
        if (empty($notice_id)) {
            wp_send_json_error(['message' => __('معرف الإشعار غير صالح', 'vmp')]);
        }

        $notices = get_user_meta($vendor_id, 'vmp_dashboard_notices', true);
        if (!is_array($notices)) {
            $notices = [];
        }

        foreach ($notices as &$notice) {
            if ($notice['id'] === $notice_id) {
                $notice['read'] = true;
                break;
            }
        }

        update_user_meta($vendor_id, 'vmp_dashboard_notices', $notices);

        wp_send_json_success(['message' => __('تم تحديد الإشعار كمقروء', 'vmp')]);
    }

    /**
     * ✅ تحديد جميع الإشعارات كمقروءة (AJAX)
     */
    public function mark_all_notices_read(): void
    {
        check_ajax_referer('vmp_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('غير مصرح', 'vmp')]);
        }

        $vendor_id = vmp_get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => __('البائع غير موجود', 'vmp')]);
        }

        $notices = get_user_meta($vendor_id, 'vmp_dashboard_notices', true);
        if (is_array($notices)) {
            foreach ($notices as &$notice) {
                $notice['read'] = true;
            }
            update_user_meta($vendor_id, 'vmp_dashboard_notices', $notices);
        }

        wp_send_json_success(['message' => __('تم تحديد جميع الإشعارات كمقروءة', 'vmp')]);
    }
}