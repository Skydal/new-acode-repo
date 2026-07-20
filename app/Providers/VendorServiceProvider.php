<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;

/**
 * مزود خدمات البائع – يسجل جميع الشورت كودات، الهوكات، وتحميل الأصول
 * ✅ النسخة النهائية المعتمدة مع جميع الإصلاحات والتحسينات
 * ✅ إضافة register_nonce لدعم تسجيل البائع
 * 
 * @package VMP\Providers
 * @since 1.0.0
 */
class VendorServiceProvider extends ServiceProvider
{
    /**
     * نقطة الدخول الرئيسية للمزود
     * يتم استدعاؤها من Kernel::boot()
     * 
     * @return void
     */
    public function boot(): void
    {
        // ─── 1. إضافة vendor_store إلى query_vars ───
        // (المصدر الوحيد — حُذفت النسخة المكررة من vendor-marketplace.php)
        add_filter('query_vars', function (array $vars): array {
            if (!in_array('vendor_store', $vars, true)) {
                $vars[] = 'vendor_store';
            }
            return $vars;
        });

        // ─── 2. استخدام قالب الصفحة العادي لعرض المتجر ───
        add_filter('template_include', function ($template) {
            if (get_query_var('vendor_store')) {
                $new_template = locate_template(['page.php', 'singular.php', 'index.php']);
                return $new_template ?: $template;
            }
            return $template;
        }, 99);

        // ─── 3. منع إعادة التوجيه غير المرغوب فيه ───
        // (المصدر الوحيد — حُذفت النسخة المكررة من vendor-marketplace.php)
        add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
            if (get_query_var('vendor_store')) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);

        // ─── 4. استبدال محتوى صفحة vendor-store بالشورت كود مع slug ───
        // (نُقل من vendor-marketplace.php ليكون هنا المصدر الوحيد)
        add_filter('the_content', function ($content) {
            if (is_page('vendor-store') && get_query_var('vendor_store')) {
                $slug = sanitize_text_field(get_query_var('vendor_store'));
                return do_shortcode('[vmp_vendor_store slug="' . esc_attr($slug) . '"]');
            }
            return $content;
        }, 10, 1);

        // ─── 5. تسجيل الشورت كودات (تعمل دائماً) ───
        $this->registerShortcodes();

        // ─── 6. التحقق من WooCommerce ───
        $woocommerceActive = $this->container->has('woocommerce.active')
            && (bool) $this->container->make('woocommerce.active');

        // ─── 6. تسجيل الهوكات المعتمدة على WooCommerce ───
        if ($woocommerceActive) {
            $this->registerWooCommerceHooks();
        }

        // ─── 7. تحميل الأصول (CSS/JS) ───
        $this->registerAssets();

        // ─── 8. عرض اسم البائع في صفحات WooCommerce العامة ───
        $this->registerVendorNameInWooCommerce();
    }

    /**
     * دالة مساعدة لعرض القوالب مع تعيين العلم والصفحة الحالية تلقائياً
     * ✅ يضمن تحميل الأصول في أي شورت كود جديد
     * ✅ يضمن دقة الصفحة الحالية مع جميع أنواع الـ permalinks
     * ✅ يسمح بتمرير متغيرات إضافية إلى القالب
     *
     * @param string $template اسم ملف القالب (مثل 'vendor-dashboard.php')
     * @param string $page     معرف الصفحة (مثل 'dashboard', 'products', 'edit-product')
     * @param array  $vars     متغيرات إضافية يتم تمريرها إلى القالب (مثل ['vendor' => $vendor])
     * @return string محتوى القالب
     */
    private function renderTemplate(string $template, string $page = '', array $vars = []): string
    {
        // تعيين العلم بأن هذه صفحة VMP (لـ Page Builders و has_shortcode)
        $GLOBALS['vmp_is_active'] = true;

        // تخزين الصفحة الحالية للاستخدام في registerAssets (موثوق مع جميع الـ permalinks)
        if ($page) {
            $GLOBALS['vmp_current_page'] = $page;
        }

        // استخراج المتغيرات إلى النطاق المحلي (آمن لأن المصدر موثوق)
        if (!empty($vars)) {
            extract($vars); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        ob_start();
        $file = VMP_PLUGIN_DIR . 'public/templates/' . $template;
        if (file_exists($file)) {
            require_once $file;
        } else {
            // رسالة خطأ في حال عدم وجود القالب (للتطوير)
            echo '<div class="vmp-notice vmp-notice-error">';
            echo sprintf(__('قالب %s غير موجود.', 'vmp'), esc_html($template));
            echo '</div>';
        }
        return ob_get_clean();
    }

    /**
     * تسجيل جميع الشورت كودات الإضافة
     *
     * @return void
     */
    private function registerShortcodes(): void
    {
        // خريطة الصفحات للوحة التحكم (يُستخدم داخل شورت كود vmp_vendor_dashboard)
        $vmp_page_map = [
            'dashboard'     => 'vendor-dashboard.php',
            'products'      => 'vendor-products.php',
            'add-product'   => 'vendor-add-product.php',
            'ai-create-product' => 'vendor-ai-create-product.php',
            'edit-product'  => 'vendor-edit-product.php',
            'orders'        => 'vendor-orders.php',
            'profile'       => 'vendor-profile.php',
            'withdrawals'   => 'vendor-withdrawals.php',
            'subscriptions' => 'vendor-subscriptions.php',
        ];

        // ── شورت كود لوحة التحكم ──
        add_shortcode('vmp_vendor_dashboard', function () use ($vmp_page_map): string {
            $page = sanitize_key($_GET['vmp_page'] ?? 'dashboard');
            $allowed_pages = array_keys($vmp_page_map);
            if (!in_array($page, $allowed_pages, true)) {
                $page = 'dashboard';
            }
            $template = $vmp_page_map[$page] ?? 'vendor-dashboard.php';
            return $this->renderTemplate($template, $page);
        });

        // ── شورت كود تسجيل البائع ──
        add_shortcode('vmp_vendor_register', function (): string {
            return $this->renderTemplate('vendor-register.php', 'register');
        });

        // ── شورت كود عرض متجر البائع ──
        add_shortcode('vmp_vendor_store', function ($atts): string {
            $atts = shortcode_atts(['slug' => '', 'id' => 0], $atts);

            // إذا لم يتم تمرير slug عبر الشورت كود، نأخذه من query_var
            if (empty($atts['slug'])) {
                $atts['slug'] = get_query_var('vendor_store', '');
            }

            $vendor_repo = $this->container->make(VendorRepositoryInterface::class);

            // البحث عن البائع
            if (!empty($atts['slug'])) {
                $vendor = $vendor_repo->findBySlug(sanitize_text_field($atts['slug']));
            } elseif (!empty($atts['id'])) {
                $vendor = $vendor_repo->find((int) $atts['id']);
            } else {
                $vendor = null;
            }

            // التحقق من وجود البائع وحالته
            if (!$vendor || $vendor->status !== 'approved') {
                return '<p class="vmp-not-found">' . __('المتجر غير موجود.', 'vmp') . '</p>';
            }

            // ✅ تمرير متغير $vendor إلى القالب
            return $this->renderTemplate('vendor-store.php', 'store', ['vendor' => $vendor]);
        });
    }

    /**
     * تسجيل الهوكات التي تعتمد على WooCommerce
     *
     * @return void
     */
    private function registerWooCommerceHooks(): void
    {
        // منع الروابط المختصرة في صفحة المتجر
        add_filter('pre_get_shortlink', static function ($shortlink, $id, $context, $allow_slugs) {
            if ('query' === $context && get_query_var('vendor_store')) {
                return '';
            }
            return $shortlink;
        }, 10, 4);

        // يمكن إضافة هوكات WooCommerce إضافية هنا مستقبلاً
        // مثال: add_filter('woocommerce_product_data_store', ...);
    }

    /**
     * تحميل أصول الإضافة (CSS/JS) – النسخة النهائية المحسّنة
     * ✅ تحميل wp_enqueue_media() شرطياً (فقط في صفحات رفع الملفات)
     * ✅ تحميل vendor-products.js شرطياً (فقط في صفحة المنتجات)
     * ✅ كائن JS واحد يحتوي كل شيء (vmp_public)
     * ✅ nonce واحد للتطبيقات العامة (vmp_public.nonce)
     * ✅ nonce خاص للتسجيل (vmp_public.register_nonce) لحل مشكلة التسجيل
     * ✅ يدعم Page Builders عبر GLOBALS['vmp_is_active']
     * ✅ يدعم جميع أنواع الـ permalinks عبر GLOBALS['vmp_current_page']
     *
     * @return void
     */
    private function registerAssets(): void
    {
        add_action('wp_enqueue_scripts', function (): void {
            // ─── 1. التحقق من أننا في صفحة VMP ───
            $is_vmp_page = !empty($GLOBALS['vmp_is_active']);

            // ─── 2. احتياطي: التحقق من post_content (للمحتوى الثابت) ───
            if (!$is_vmp_page && !empty($GLOBALS['post'])) {
                $content = $GLOBALS['post']->post_content ?? '';
                $shortcodes = ['vmp_vendor_register', 'vmp_vendor_dashboard', 'vmp_vendor_store'];
                foreach ($shortcodes as $sc) {
                    if (has_shortcode($content, $sc)) {
                        $is_vmp_page = true;
                        break;
                    }
                }
            }

            // ─── 3. إذا لم تكن صفحة VMP، لا نحمّل أي أصول ───
            if (!$is_vmp_page) {
                return;
            }

            // ─── 4. الحصول على الصفحة الحالية (موثوق مع جميع أنواع الـ permalinks) ───
            $current_page = $GLOBALS['vmp_current_page'] 
                ?? sanitize_key($_GET['vmp_page'] ?? 'dashboard');

            // ─── 5. تحميل wp_enqueue_media() شرطياً (فقط عند الحاجة) ───
            $pages_needing_media = ['add-product', 'edit-product', 'profile', 'ai-create-product'];
            if (in_array($current_page, $pages_needing_media, true)) {
                wp_enqueue_media();
            }

            // ─── 6. تحميل ملفات التصميم (CSS) ───
            wp_enqueue_style(
                'vmp-public',
                VMP_PLUGIN_URL . 'public/css/public.css',
                [],
                VMP_VERSION
            );

            // ─── 7. تحميل ملف JavaScript العام (يُحمّل في كل صفحات VMP) ───
            wp_enqueue_script(
                'vmp-public',
                VMP_PLUGIN_URL . 'public/js/public.js',
                ['jquery', 'media-editor'],
                VMP_VERSION,
                true
            );

            // ─── 8. تحميل JS المنتجات فقط في صفحة المنتجات ───
            if ($current_page === 'products') {
                wp_enqueue_script(
                    'vmp-products-js',
                    VMP_PLUGIN_URL . 'public/js/vendor-products.js',
                    ['jquery', 'vmp-public'],
                    VMP_VERSION,
                    true
                );
            }

            if ($current_page === 'ai-create-product') {
                wp_enqueue_script(
                    'vmp-ai-product-js',
                    VMP_PLUGIN_URL . 'public/js/vendor-ai-product.js',
                    ['jquery', 'vmp-public'],
                    VMP_VERSION,
                    true
                );
            }

            // ─── 9. كائن واحد يحتوي كل شيء (بدون تكرار) ───
            wp_localize_script('vmp-public', 'vmp_public', [
                'ajax_url'       => admin_url('admin-ajax.php'),
                'nonce'          => wp_create_nonce('vmp_public_nonce'), // ✅ nonce عام
                'register_nonce' => wp_create_nonce('vmp_vendor_register_nonce'), // ✅ nonce خاص بالتسجيل
                'page'           => $current_page, // ✅ الصفحة الحالية (موثوقة)
                'plugin_url'     => VMP_PLUGIN_URL,
                'dashboard_url'  => home_url('/vendor-dashboard/'),
                'strings'        => [
                    'loading'        => __('جاري...', 'vmp'),
                    'delete'         => __('حذف', 'vmp'),
                    'error'          => __('حدث خطأ', 'vmp'),
                    'conn_error'     => __('حدث خطأ في الاتصال', 'vmp'),
                    'confirm_delete' => __('هل أنت متأكد من حذف هذا المنتج؟', 'vmp'),
                    'next'           => __('التالي', 'vmp'),
                    'prev'           => __('السابق', 'vmp'),
                    'submit'         => __('إرسال الطلب', 'vmp'),
                ],
            ]);
        });
    }

    /**
     * عرض اسم البائع في صفحات WooCommerce العامة
     * (صفحة أرشيف المنتجات + صفحة المنتج الفردي)
     * ✅ يستخدم الدوال المساعدة من helpers.php
     * ✅ يضيف رابطاً إلى صفحة متجر البائع
     *
     * @return void
     */
    private function registerVendorNameInWooCommerce(): void
    {
        // ── عرض اسم البائع تحت عنوان المنتج في صفحة المتجر (أرشيف المنتجات) ──
        add_action('woocommerce_after_shop_loop_item_title', function () {
            global $product;
            if (!$product) {
                return;
            }

            $vendor_id = vmp_get_product_vendor_id($product->get_id());
            if (!$vendor_id) {
                return;
            }

            $vendor = vmp_get_vendor($vendor_id);
            if (!$vendor || $vendor->status !== 'approved') {
                return;
            }

            echo '<div class="vmp-product-vendor" style="font-size: 12px; color: #64748b; margin-top: 2px; margin-bottom: 6px;">';
            echo sprintf(
                __('بواسطة %s', 'vmp'),
                '<a href="' . home_url('/store/' . $vendor->store_slug) . '" style="color: #6366f1; text-decoration: none;">' . esc_html($vendor->store_name) . '</a>'
            );
            echo '</div>';
        }, 6);

        // ── عرض اسم البائع في صفحة المنتج الفردي (تفاصيل المنتج) ──
        add_action('woocommerce_single_product_summary', function () {
            global $product;
            if (!$product) {
                return;
            }

            $vendor_id = vmp_get_product_vendor_id($product->get_id());
            if (!$vendor_id) {
                return;
            }

            $vendor = vmp_get_vendor($vendor_id);
            if (!$vendor || $vendor->status !== 'approved') {
                return;
            }

            echo '<div class="vmp-product-vendor-single" style="font-size: 14px; color: #64748b; margin: 8px 0; border-top: 1px solid #e2e8f0; padding-top: 12px;">';
            echo sprintf(
                __('البائع: %s', 'vmp'),
                '<a href="' . home_url('/store/' . $vendor->store_slug) . '" style="color: #6366f1; text-decoration: none; font-weight: 600;">' . esc_html($vendor->store_name) . '</a>'
            );
            echo '</div>';
        }, 6);
    }
}
