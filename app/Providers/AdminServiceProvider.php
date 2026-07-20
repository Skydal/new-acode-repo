<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

/**
 * Class AdminServiceProvider
 *
 * مسؤول عن تسجيل قوائم واجهة المشرف (Admin Menu) وإضافة الصفحات الفرعية
 * وتحميل الأصول (CSS/JS) الخاصة بلوحة التحكم.
 *
 * @package vendor-marketplace
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * Boot functionality helper.
     *
     * تسجيل قائمة الإضافة في لوحة تحكم ووردبريس مع جميع الصفحات الفرعية.
     *
     * @return void
     */
    public function boot(): void
    {
        // ── قائمة الإضافة الرئيسية ──
        add_action('admin_menu', static function (): void {
            add_menu_page(
                __('Vendor Marketplace', 'vmp'),
                __('Vendor Marketplace', 'vmp'),
                'vmp_manage_vendors',
                'vmp-dashboard',
                static function (): void {
                    require_once VMP_PLUGIN_DIR . 'admin/pages/dashboard.php';
                },
                'dashicons-store',
                30
            );

            // ── الصفحات الفرعية ──
            $sub_pages = [
                ['vmp-dashboard',     __('لوحة التحكم', 'vmp'),  __('لوحة التحكم', 'vmp'),  'vmp_manage_vendors',      'dashboard.php'],
                ['vmp-vendors',       __('البائعون', 'vmp'),       __('البائعون', 'vmp'),       'vmp_manage_vendors',      'vendors.php'],
                ['vmp-products',      __('المنتجات', 'vmp'),       __('المنتجات', 'vmp'),       'vmp_manage_products',     'products.php'],
                ['vmp-orders',        __('الطلبات', 'vmp'),        __('الطلبات', 'vmp'),        'vmp_manage_orders',       'orders.php'],
                ['vmp-commissions',   __('العمولات', 'vmp'),       __('العمولات', 'vmp'),       'vmp_manage_commissions',  'commissions.php'],
                ['vmp-withdrawals',   __('السحوبات', 'vmp'),       __('السحوبات', 'vmp'),       'vmp_manage_withdrawals',  'withdrawals.php'],
                ['vmp-subscriptions', __('الاشتراكات', 'vmp'),    __('الاشتراكات', 'vmp'),    'vmp_manage_subscriptions','subscriptions.php'],
                // ✅ إضافة صفحة إعدادات الذكاء الاصطناعي
                ['vmp-ai-settings',   __('إعدادات الذكاء الاصطناعي', 'vmp'), __('الذكاء الاصطناعي', 'vmp'), 'vmp_manage_settings', 'ai-settings.php'],
                ['vmp-settings',      __('الإعدادات', 'vmp'),     __('الإعدادات', 'vmp'),     'vmp_manage_settings',     'settings.php'],
                ['vmp-whatsapp-stats', __('إحصائيات واتساب', 'vmp'), __('واتساب', 'vmp'), 'vmp_manage_reports', 'whatsapp-stats.php'],
            ];

            foreach ($sub_pages as $page) {
                $file = $page[4];
                add_submenu_page(
                    'vmp-dashboard',
                    $page[1],
                    $page[2],
                    $page[3],
                    $page[0],
                    static function () use ($file): void {
                        $path = VMP_PLUGIN_DIR . 'admin/pages/' . $file;
                        if (file_exists($path)) {
                            require_once $path;
                        } else {
                            echo '<div class="notice notice-error"><p>' . sprintf(__('الملف %s غير موجود.', 'vmp'), esc_html($file)) . '</p></div>';
                        }
                    }
                );
            }
        });

        // ── تحميل الأصول (CSS/JS) في صفحات الإدارة ──
        add_action('admin_enqueue_scripts', static function ($hook): void {
            // تحميل فقط في صفحات VMP
            if (strpos($hook, 'vmp') === false) {
                return;
            }

            // ── الأنماط (CSS) ──
            wp_enqueue_style('vmp-admin', VMP_PLUGIN_URL . 'admin/css/admin.css', [], VMP_VERSION);

            // ── السكربتات (JS) ──
            wp_enqueue_script('vmp-admin', VMP_PLUGIN_URL . 'admin/js/admin.js', ['jquery', 'wp-i18n'], VMP_VERSION, true);

            // ── إعدادات الـ JavaScript (vmp_admin object) ──
            wp_localize_script('vmp-admin', 'vmp_admin', [
                'ajax_url'   => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('vmp_admin_nonce'),
                'plugin_url' => VMP_PLUGIN_URL,
                'strings'    => [
                    'confirm_approve' => __('هل أنت متأكد من الموافقة؟', 'vmp'),
                    'confirm_reject'  => __('هل أنت متأكد من الرفض؟', 'vmp'),
                    'confirm_delete'  => __('هل أنت متأكد من الحذف؟', 'vmp'),
                    'loading'         => __('جاري التحميل...', 'vmp'),
                    'error'           => __('حدث خطأ، يرجى المحاولة مرة أخرى.', 'vmp'),
                ],
            ]);

            // ── تحميل Chart.js في صفحات التقارير ولوحة التحكم ──
            if (strpos($hook, 'vmp-dashboard') !== false || strpos($hook, 'vmp-reports') !== false) {
                wp_enqueue_script('chart-js', VMP_PLUGIN_URL . 'assets/js/chart.min.js', [], '4.4.0', true);
            }
        });
    }
}