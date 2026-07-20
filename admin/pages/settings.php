<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('vmp_manage_settings')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

// ── جلب الإعدادات ──
$settings = get_option('vmp_settings', []);
$general  = $settings['general'] ?? [];
$finance  = $settings['finance'] ?? [];
$display  = $settings['display'] ?? [];
?>

<div class="wrap vmp-admin-wrap">
    <div class="vmp-admin-header">
        <h1><?php _e('إعدادات Vendor Marketplace', 'vmp'); ?></h1>
    </div>

    <!-- رسائل الإشعارات -->
    <div id="vmp-settings-notice" style="display:none;" class="notice"></div>

    <!-- التبويبات -->
    <div class="vmp-admin-tabs">
        <a href="#tab-general" class="vmp-admin-tab active" data-tab="general"><?php _e('عام', 'vmp'); ?></a>
        <a href="#tab-finance" class="vmp-admin-tab" data-tab="finance"><?php _e('المالية', 'vmp'); ?></a>
        <a href="#tab-display" class="vmp-admin-tab" data-tab="display"><?php _e('المظهر والصفحات', 'vmp'); ?></a>
    </div>

    <div class="vmp-card">
        <div class="vmp-card-body">
            <form id="vmp-settings-form" class="vmp-ajax-form" data-action="vmp_admin_save_settings">
                <?php wp_nonce_field('vmp_admin_nonce', 'nonce'); ?>
                <input type="hidden" name="vmp_settings[general][enable_registration]" value="0">
                <input type="hidden" name="vmp_settings[general][auto_approve_vendors]" value="0">
                <input type="hidden" name="vmp_settings[general][auto_approve_products]" value="0">
                <input type="hidden" name="vmp_settings[finance][enable_subscriptions]" value="0">

                <!-- تبويب عام -->
                <div id="tab-general" class="vmp-tab-content active">
                    <h2><?php _e('الإعدادات العامة', 'vmp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('تسجيل البائعين', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[general][enable_registration]" value="1" <?php checked(($general['enable_registration'] ?? '') === '1'); ?>>
                                    <?php _e('السماح للبائعين الجدد بالتسجيل', 'vmp'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('الموافقة التلقائية على البائعين', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[general][auto_approve_vendors]" value="1" <?php checked(($general['auto_approve_vendors'] ?? '') === '1'); ?>>
                                    <?php _e('الموافقة التلقائية على البائعين الجدد', 'vmp'); ?>
                                </label>
                            </td>
                        </tr>
                        <!-- ✅ ميزة النشر بدون مراجعة -->
                        <tr>
                            <th scope="row"><label><?php _e('الموافقة على المنتجات', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[general][auto_approve_products]" value="1" <?php checked(($general['auto_approve_products'] ?? '') === '1'); ?>>
                                    <?php _e('الموافقة التلقائية على المنتجات الجديدة المضافة بواسطة البائعين (نشر بدون مراجعة)', 'vmp'); ?>
                                </label>
                                <p class="description"><?php _e('عند تفعيل هذا الخيار، سيتم نشر منتجات البائعين مباشرة دون الحاجة إلى موافقة المشرف.', 'vmp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- تبويب المالية -->
                <div id="tab-finance" class="vmp-tab-content" style="display:none;">
                    <h2><?php _e('الإعدادات المالية والعمولات', 'vmp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('نسبة العمولة الافتراضية (%)', 'vmp'); ?></label></th>
                            <td>
                                <input type="number" step="0.01" name="vmp_settings[finance][default_commission]" class="regular-text" value="<?php echo esc_attr($finance['default_commission'] ?? '10'); ?>">
                                <p class="description"><?php _e('تطبق في حال لم يمتلك البائع خطة اشتراك خاصة.', 'vmp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('الحد الأدنى للسحب', 'vmp'); ?></label></th>
                            <td>
                                <input type="number" step="1" name="vmp_settings[finance][min_withdrawal]" class="regular-text" value="<?php echo esc_attr($finance['min_withdrawal'] ?? '100'); ?>">
                                <p class="description"><?php _e('الحد الأدنى لرصيد البائع ليتمكن من طلب سحب.', 'vmp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('تفعيل الاشتراكات', 'vmp'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vmp_settings[finance][enable_subscriptions]" value="1" <?php checked(($finance['enable_subscriptions'] ?? '') === '1'); ?>>
                                    <?php _e('تفعيل نظام خطط الاشتراكات للبائعين', 'vmp'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- تبويب المظهر -->
                <div id="tab-display" class="vmp-tab-content" style="display:none;">
                    <h2><?php _e('إعدادات الصفحات', 'vmp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('صفحة تسجيل البائع', 'vmp'); ?></label></th>
                            <td>
                                <?php wp_dropdown_pages([
                                    'name' => 'vmp_settings[display][register_page]',
                                    'selected' => $display['register_page'] ?? '',
                                    'show_option_none' => __('— اختر صفحة —', 'vmp'),
                                    'option_none_value' => '',
                                ]); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('لوحة تحكم البائع', 'vmp'); ?></label></th>
                            <td>
                                <?php wp_dropdown_pages([
                                    'name' => 'vmp_settings[display][dashboard_page]',
                                    'selected' => $display['dashboard_page'] ?? '',
                                    'show_option_none' => __('— اختر صفحة —', 'vmp'),
                                    'option_none_value' => '',
                                ]); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large" id="vmp-save-settings-btn">
                        <?php _e('حفظ الإعدادات', 'vmp'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // ── التبديل بين التبويبات ──
    $(document).on('click', '.vmp-admin-tab', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.vmp-admin-tab').removeClass('active');
        $(this).addClass('active');
        $('.vmp-tab-content').hide();
        $('#tab-' + tab).show();
    });

    // ── حفظ الإعدادات ──
    $('#vmp-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $('#vmp-save-settings-btn');
        var $notice = $('#vmp-settings-notice');

        // تعطيل الزر وعرض رسالة التحميل
        $btn.prop('disabled', true).text('<?php _e('جاري الحفظ...', 'vmp'); ?>');
        $notice.hide().removeClass('notice-success notice-error');

        // جمع بيانات النموذج وتحويلها إلى بنية متداخلة
        var formData = $form.serializeArray();
        var data = {};

        $.each(formData, function(i, field) {
            var name = field.name;
            var value = field.value;

            if (name.startsWith('vmp_settings[')) {
                var parts = name.replace(/\]/g, '').split('[');
                if (parts.length >= 3) {
                    var section = parts[1];
                    var key = parts[2];
                    if (!data[section]) data[section] = {};
                    data[section][key] = value;
                }
            } else if (name === 'nonce') {
                data.nonce = value;
            } else if (name === 'action') {
                data.action = value;
            }
        });

        // ضمان إرسال checkbox غير المحددة بقيمة 0
        if (!data.general) data.general = {};
        if (!data.finance) data.finance = {};
        if (!data.display) data.display = {};

        // التأكد من وجود جميع الحقول (حتى الغير محددة)
        ['enable_registration', 'auto_approve_vendors', 'auto_approve_products'].forEach(function(key) {
            if (data.general[key] === undefined) data.general[key] = '0';
        });
        if (data.finance.enable_subscriptions === undefined) data.finance.enable_subscriptions = '0';

        // إرسال الطلب
        $.post(ajaxurl, {
            action: 'vmp_admin_save_settings',
            nonce: data.nonce,
            vmp_settings: {
                general: data.general,
                finance: data.finance,
                display: data.display
            }
        }, function(response) {
            $btn.prop('disabled', false).text('<?php _e('حفظ الإعدادات', 'vmp'); ?>');

            if (response.success) {
                $notice.show().addClass('notice-success').html('<p>' + response.data.message + '</p>');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $notice.show().addClass('notice-error').html('<p>' + response.data.message + '</p>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('<?php _e('حفظ الإعدادات', 'vmp'); ?>');
            $notice.show().addClass('notice-error').html('<p><?php _e('حدث خطأ في الاتصال بالخادم.', 'vmp'); ?></p>');
        });
    });
});
</script>

<style>
.vmp-admin-wrap .vmp-admin-tabs {
    display: flex;
    gap: 4px;
    margin: 20px 0 0;
    background: #f1f5f9;
    padding: 8px 8px 0;
    border-radius: 8px 8px 0 0;
}
.vmp-admin-wrap .vmp-admin-tab {
    padding: 10px 20px;
    background: transparent;
    color: #64748b;
    text-decoration: none;
    border-radius: 6px 6px 0 0;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    cursor: pointer;
}
.vmp-admin-wrap .vmp-admin-tab:hover {
    background: #e2e8f0;
    color: #1e293b;
}
.vmp-admin-wrap .vmp-admin-tab.active {
    background: #ffffff;
    color: #6366f1;
    box-shadow: 0 -2px 8px rgba(99,102,241,0.1);
}
.vmp-admin-wrap .vmp-tab-content {
    background: #ffffff;
    padding: 24px;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 8px 8px;
}
.vmp-admin-wrap .vmp-tab-content h2 {
    margin-top: 0;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}
.vmp-admin-wrap .form-table th {
    width: 220px;
    padding: 20px 10px 20px 0;
    font-weight: 600;
    font-size: 13px;
}
.vmp-admin-wrap .form-table td {
    padding: 20px 0;
}
.vmp-admin-wrap .description {
    color: #64748b;
    font-size: 12px;
    margin-top: 4px;
}
.vmp-admin-wrap .submit {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}
.vmp-admin-wrap .notice {
    margin: 16px 0;
}
@media (max-width: 768px) {
    .vmp-admin-wrap .vmp-admin-tabs {
        flex-wrap: wrap;
        gap: 0;
        padding: 4px;
    }
    .vmp-admin-wrap .vmp-admin-tab {
        flex: 1;
        text-align: center;
        padding: 8px 12px;
        font-size: 12px;
    }
    .vmp-admin-wrap .form-table th {
        width: 100%;
        display: block;
        padding-bottom: 0;
    }
    .vmp-admin-wrap .form-table td {
        display: block;
        padding-top: 4px;
    }
}
</style>