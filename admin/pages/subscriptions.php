<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('manage_options')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

// ── جلب جميع الخطط ──
$plan_repo = new \VMP\Repositories\SubscriptionPlanRepository();
$plans = $plan_repo->getAll(false);
?>

<div class="wrap vmp-admin-wrap">
    <h1 class="wp-heading-inline"><?php _e('خطط الاشتراك', 'vmp'); ?></h1>
    <button class="page-title-action vmp-open-modal"><?php _e('إضافة خطة جديدة', 'vmp'); ?></button>
    <hr class="wp-header-end">

    <!-- ✅ استخدام نظام الإشعارات الموحد -->
    <div id="vmp-admin-notice" style="display:none;" class="notice"></div>

    <!-- ── جدول الخطط ── -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('الاسم', 'vmp'); ?></th>
                <th><?php _e('السعر', 'vmp'); ?></th>
                <th><?php _e('المدة', 'vmp'); ?></th>
                <th><?php _e('العمولة', 'vmp'); ?></th>
                <th><?php _e('الحد الأقصى', 'vmp'); ?></th>
                <th><?php _e('الحالة', 'vmp'); ?></th>
                <th><?php _e('إجراءات', 'vmp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($plans)) : ?>
                <tr><td colspan="7" style="text-align:center;"><?php _e('لا توجد خطط.', 'vmp'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($plans as $plan) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($plan->name); ?></strong></td>
                        <td><?php echo wc_price($plan->price); ?></td>
                        <td><?php echo $plan->billing_period === 'month' ? __('شهري', 'vmp') : __('سنوي', 'vmp'); ?></td>
                        <td><?php echo (float) $plan->commission_rate; ?>%</td>
                        <td><?php echo (int) $plan->max_products === -1 ? __('غير محدود', 'vmp') : (int) $plan->max_products; ?></td>
                        <td>
                            <span class="vmp-badge-status <?php echo $plan->is_active ? 'vmp-status-approved' : 'vmp-status-rejected'; ?>">
                                <?php echo $plan->is_active ? __('مفعل', 'vmp') : __('معطل', 'vmp'); ?>
                            </span>
                        </td>
                        <td>
                            <button class="button vmp-edit-plan" data-plan='<?php echo json_encode($plan); ?>'><?php _e('تعديل', 'vmp'); ?></button>
                            <button class="button vmp-delete-plan" data-id="<?php echo (int) $plan->id; ?>" data-nonce="<?php echo wp_create_nonce('vmp_admin_nonce'); ?>"><?php _e('حذف', 'vmp'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ════════════════════════════════════════════════ -->
    <!-- ✅ طلبات تغيير الخطة المعلقة -->
    <!-- ════════════════════════════════════════════════ -->
    <div class="vmp-admin-card" style="margin-top: 30px; background: #fff; border-radius: 8px; padding: 0 20px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div class="vmp-admin-card-header" style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding: 16px 0;">
            <h2 style="margin:0; font-size: 18px; font-weight: 600;">⏳ <?php _e('طلبات تغيير الخطة المعلقة', 'vmp'); ?></h2>
            <span class="vmp-admin-badge" id="vmp-pending-count" style="background: #6366f1; color: #fff; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600;">0</span>
        </div>
        <div class="vmp-admin-card-body" style="padding: 16px 0 0;">
            <div id="vmp-pending-requests">
                <p style="text-align:center; padding: 20px; color: #94a3b8;">
                    <?php _e('جاري تحميل الطلبات...', 'vmp'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════ -->
    <!-- مودال الإضافة / التعديل -->
    <!-- ════════════════════════════════════════════════ -->
    <div class="vmp-modal-overlay" id="vmp-plan-modal" style="display:none;">
        <div class="vmp-modal">
            <div class="vmp-modal-header">
                <h2 id="vmp-modal-title"><?php _e('إضافة خطة جديدة', 'vmp'); ?></h2>
                <button class="vmp-modal-close">&times;</button>
            </div>
            <div class="vmp-modal-body">
                <form id="vmp-plan-form">
                    <input type="hidden" name="plan_id" id="vmp_plan_id" value="0">
                    <?php wp_nonce_field('vmp_admin_nonce', 'nonce'); ?>

                    <!-- ── اسم الخطة ── -->
                    <div class="vmp-field-group">
                        <label><?php _e('اسم الخطة', 'vmp'); ?> <span class="required">*</span></label>
                        <input type="text" name="name" id="vmp_plan_name" class="vmp-field" required>
                    </div>

                    <!-- ── الوصف ── -->
                    <div class="vmp-field-group">
                        <label><?php _e('وصف الخطة', 'vmp'); ?></label>
                        <textarea name="description" id="vmp_plan_description" rows="2" class="vmp-field"></textarea>
                    </div>

                    <!-- ── السعر ودورة الدفع ── -->
                    <div class="vmp-row">
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('السعر', 'vmp'); ?> <span class="required">*</span></label>
                                <input type="number" step="0.01" name="price" id="vmp_plan_price" class="vmp-field" required>
                            </div>
                        </div>
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('دورة الدفع', 'vmp'); ?> <span class="required">*</span></label>
                                <select name="billing_period" id="vmp_plan_billing_period" class="vmp-field">
                                    <option value="month"><?php _e('شهري', 'vmp'); ?></option>
                                    <option value="year"><?php _e('سنوي', 'vmp'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ── العمولة والحد الأقصى ── -->
                    <div class="vmp-row">
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('نسبة العمولة (%)', 'vmp'); ?> <span class="required">*</span></label>
                                <input type="number" step="0.1" name="commission_rate" id="vmp_plan_commission_rate" class="vmp-field" value="10" required>
                                <span class="vmp-hint"><?php _e('النسبة التي يقتطعها الموقع.', 'vmp'); ?></span>
                            </div>
                        </div>
                        <div class="vmp-col">
                            <div class="vmp-field-group">
                                <label><?php _e('الحد الأقصى للمنتجات', 'vmp'); ?></label>
                                <input type="number" name="max_products" id="vmp_plan_max_products" class="vmp-field" value="0">
                                <span class="vmp-hint"><?php _e('0 = غير محدود', 'vmp'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- ── الحالة ── -->
                    <div class="vmp-field-group">
                        <label><?php _e('الحالة', 'vmp'); ?> <span class="required">*</span></label>
                        <select name="is_active" id="vmp_plan_is_active" class="vmp-field">
                            <option value="1"><?php _e('مفعل', 'vmp'); ?></option>
                            <option value="0"><?php _e('معطل', 'vmp'); ?></option>
                        </select>
                    </div>

                    <!-- ── المميزات (Toggle Buttons) ── -->
                    <div class="vmp-features-section">
                        <label><?php _e('المميزات', 'vmp'); ?></label>
                        <div class="vmp-features-grid">
                            <?php
                            $feature_list = [
                                'whatsapp_button'   => ['icon' => '💬', 'label' => __('طلب عبر واتساب', 'vmp')],
                                'store_address'     => ['icon' => '📍', 'label' => __('عنوان مع خريطة', 'vmp')],
                                'social_links'      => ['icon' => '🔗', 'label' => __('روابط التواصل', 'vmp')],
                                'product_video'     => ['icon' => '🎬', 'label' => __('فيديو تعريفي', 'vmp')],
                                'unlimited_products'=> ['icon' => '♾️', 'label' => __('منتجات غير محدودة', 'vmp')],
                                'custom_domain'     => ['icon' => '🌐', 'label' => __('نطاق مخصص', 'vmp')],
                                'advanced_analytics'=> ['icon' => '📊', 'label' => __('تحليلات متقدمة', 'vmp')],
                                'coupons'           => ['icon' => '🏷️', 'label' => __('كوبونات خصم', 'vmp')],
                                'trusted_badge'     => ['icon' => '⭐', 'label' => __('شارة موثوق', 'vmp')],
                                'priority_support'  => ['icon' => '🛟', 'label' => __('دعم أولوية', 'vmp')],
                            ];
                            foreach ($feature_list as $key => $feature) :
                            ?>
                                <label class="vmp-feature-toggle" data-feature="<?php echo esc_attr($key); ?>">
                                    <input type="checkbox" name="features[<?php echo esc_attr($key); ?>]" value="1" class="vmp-feature-input">
                                    <span class="vmp-toggle-slider"></span>
                                    <span class="vmp-feature-label">
                                        <span class="vmp-feature-icon"><?php echo esc_html($feature['icon']); ?></span>
                                        <?php echo esc_html($feature['label']); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="vmp-hint"><?php _e('اختر الميزات التي ستكون متاحة في هذه الخطة.', 'vmp'); ?></p>
                    </div>

                    <!-- ── أزرار الإجراء ── -->
                    <div class="vmp-actions">
                        <button type="button" class="vmp-btn vmp-btn-secondary vmp-modal-cancel"><?php _e('إلغاء', 'vmp'); ?></button>
                        <button type="submit" class="vmp-btn vmp-btn-primary" id="vmp-save-plan-btn">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('حفظ الخطة', 'vmp'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════ -->
<!-- الأنماط المخصصة -->
<!-- ════════════════════════════════════════════════ -->
<style>
/* ── الحقول الأساسية ── */
.vmp-field-group {
    margin-bottom: 16px;
}
.vmp-field-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 4px;
}
.vmp-field-group .required {
    color: #ef4444;
}
.vmp-field {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #fafbff;
}
.vmp-field:focus {
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.vmp-hint {
    display: block;
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
}
.vmp-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* ── مودال ── */
.vmp-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15,23,42,0.6);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.vmp-modal {
    background: #ffffff;
    border-radius: 16px;
    max-width: 680px;
    width: 100%;
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: 0 24px 64px rgba(0,0,0,0.25);
    animation: vmpModalIn 0.3s ease;
}
@keyframes vmpModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.vmp-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 28px;
    border-bottom: 1px solid #e2e8f0;
}
.vmp-modal-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}
.vmp-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #94a3b8;
    padding: 0 8px;
    transition: color 0.2s;
    line-height: 1;
}
.vmp-modal-close:hover {
    color: #0f172a;
}
.vmp-modal-body {
    padding: 28px;
}

/* ── أزرار التبديل (Toggles) ── */
.vmp-features-section {
    margin: 16px 0 8px;
}
.vmp-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
    background: #f8fafc;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.vmp-feature-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    position: relative;
    user-select: none;
}
.vmp-feature-toggle:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.vmp-feature-toggle .vmp-feature-input {
    display: none;
}
.vmp-toggle-slider {
    width: 36px;
    height: 20px;
    background: #cbd5e1;
    border-radius: 9999px;
    position: relative;
    transition: background 0.3s;
    flex-shrink: 0;
}
.vmp-toggle-slider::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: #ffffff;
    border-radius: 50%;
    transition: transform 0.3s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.vmp-feature-input:checked + .vmp-toggle-slider {
    background: #6366f1;
}
.vmp-feature-input:checked + .vmp-toggle-slider::after {
    transform: translateX(16px);
}
.vmp-feature-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
}
.vmp-feature-icon {
    font-size: 16px;
}

/* ── الأزرار ── */
.vmp-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}
.vmp-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.vmp-btn-primary {
    background: #6366f1;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.vmp-btn-primary:hover {
    background: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99,102,241,0.35);
}
.vmp-btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.vmp-btn-secondary {
    background: #f1f5f9;
    color: #475569;
}
.vmp-btn-secondary:hover {
    background: #e2e8f0;
}

/* ── حالات الجدول ── */
.vmp-badge-status {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
}
.vmp-status-approved { background: #d4edda; color: #155724; }
.vmp-status-rejected { background: #f8d7da; color: #721c24; }

/* ── تحسين شكل الجدول الجديد ── */
.vmp-admin-card .wp-list-table {
    margin-top: 0;
}
.vmp-admin-card .button {
    margin: 2px;
}

/* ── استجابة للشاشات الصغيرة ── */
@media (max-width: 600px) {
    .vmp-row {
        grid-template-columns: 1fr;
    }
    .vmp-modal {
        margin: 10px;
    }
    .vmp-modal-body {
        padding: 16px;
    }
    .vmp-features-grid {
        grid-template-columns: 1fr;
        padding: 12px;
    }
    .vmp-actions {
        flex-wrap: wrap;
    }
    .vmp-actions .vmp-btn {
        flex: 1;
        justify-content: center;
    }
}
</style>

<!-- ════════════════════════════════════════════════ -->
<!-- JavaScript -->
<!-- ════════════════════════════════════════════════ -->
<script>
jQuery(document).ready(function($) {
    'use strict';

    // ── فتح المودال للتعديل ──
    $(document).on('click', '.vmp-edit-plan', function(e) {
        e.preventDefault();
        var plan = $(this).data('plan');
        if (!plan) return;

        $('#vmp_plan_id').val(plan.id);
        $('#vmp_plan_name').val(plan.name);
        $('#vmp_plan_description').val(plan.description || '');
        $('#vmp_plan_price').val(plan.price);
        $('#vmp_plan_billing_period').val(plan.billing_period);
        $('#vmp_plan_commission_rate').val(plan.commission_rate);
        $('#vmp_plan_max_products').val(plan.max_products);
        $('#vmp_plan_is_active').val(plan.is_active);

        // تعبئة الـ Toggles من الميزات
        var features = plan.features ? JSON.parse(plan.features) : {};
        $('.vmp-feature-input').prop('checked', false);
        $.each(features, function(key, value) {
            if (value === true || value === 1) {
                $('input[name="features[' + key + ']"]').prop('checked', true);
            }
        });

        $('#vmp-modal-title').text('<?php _e('تعديل الخطة', 'vmp'); ?>');
        $('#vmp-plan-modal').show();
    });

    // ── فتح المودال للإضافة ──
    $(document).on('click', '.vmp-open-modal', function(e) {
        e.preventDefault();
        $('#vmp-plan-form')[0].reset();
        $('#vmp_plan_id').val('0');
        $('.vmp-feature-input').prop('checked', false);
        $('#vmp-modal-title').text('<?php _e('إضافة خطة جديدة', 'vmp'); ?>');
        $('#vmp-plan-modal').show();
    });

    // ── إغلاق المودال ──
    $(document).on('click', '.vmp-modal-close, .vmp-modal-cancel', function() {
        $('#vmp-plan-modal').hide();
    });
    $(document).on('click', '#vmp-plan-modal', function(e) {
        if ($(e.target).is(this)) $(this).hide();
    });

    // ── حفظ الخطة ──
    $('#vmp-plan-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $('#vmp-save-plan-btn');
        var $notice = $('#vmp-admin-notice');
        var planId = $('#vmp_plan_id').val();
        var action = planId > 0 ? 'vmp_admin_update_plan' : 'vmp_admin_create_plan';

        // جمع البيانات الأساسية
        var data = {
            name: $('#vmp_plan_name').val(),
            description: $('#vmp_plan_description').val(),
            price: $('#vmp_plan_price').val(),
            billing_period: $('#vmp_plan_billing_period').val(),
            commission_rate: $('#vmp_plan_commission_rate').val(),
            max_products: $('#vmp_plan_max_products').val(),
            is_active: $('#vmp_plan_is_active').val(),
            plan_id: planId,
            nonce: $('input[name="nonce"]').val(),
            action: action
        };

        // جمع الميزات من الـ Toggles
        var features = {};
        $('.vmp-feature-input:checked').each(function() {
            var key = $(this).attr('name').replace(/^features\[|\]$/g, '');
            features[key] = true;
        });
        data.features = features;

        // تعطيل الزر وعرض رسالة التحميل
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> <?php _e('جاري الحفظ...', 'vmp'); ?>');
        $notice.hide();

        $.post(ajaxurl, data, function(res) {
            $btn.prop('disabled', false).html(originalText);
            if (res.success) {
                $notice.show().addClass('notice-success').html('<p>' + res.data.message + '</p>');
                setTimeout(function() { location.reload(); }, 1200);
            } else {
                $notice.show().addClass('notice-error').html('<p>' + res.data.message + '</p>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalText);
            $notice.show().addClass('notice-error').html('<p><?php _e('خطأ في الاتصال بالخادم.', 'vmp'); ?></p>');
        });
    });

    // ── حذف خطة ──
    $(document).on('click', '.vmp-delete-plan', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        var nonce = $btn.data('nonce');

        if (!confirm('<?php _e('هل أنت متأكد من حذف هذه الخطة نهائياً؟', 'vmp'); ?>')) return;

        $btn.text('<?php _e('جاري...', 'vmp'); ?>');
        $.post(ajaxurl, {
            action: 'vmp_admin_delete_plan',
            plan_id: id,
            nonce: nonce
        }, function(res) {
            if (res.success) location.reload();
            else alert(res.data.message);
        }).fail(function() {
            alert('<?php _e('خطأ في الاتصال.', 'vmp'); ?>');
            $btn.text('<?php _e('حذف', 'vmp'); ?>');
        });
    });

    /* ════════════════════════════════════════════════ */
    /* ✅ طلبات تغيير الخطة - JavaScript إضافي         */
    /* ════════════════════════════════════════════════ */

    // ── جلب طلبات تغيير الخطة المعلقة ──
    /**
     * LoadPendingRequests functionality helper.
     *
     * @return void Output payload.
     */
    function loadPendingRequests() {
        $.post(ajaxurl, {
            action: 'vmp_get_pending_plan_changes',
            nonce: '<?php echo wp_create_nonce('vmp_admin_nonce'); ?>'
        }, function(response) {
            if (response.success && response.data.requests) {
                var requests = response.data.requests;
                var html = '';

                if (requests.length === 0) {
                    html = '<p style="text-align:center; padding: 20px; color: #94a3b8;">' +
                           '<?php _e('لا توجد طلبات معلقة.', 'vmp'); ?>' +
                           '</p>';
                } else {
                    html = '<table class="wp-list-table widefat fixed striped">' +
                           '<thead><tr>' +
                           '<th><?php _e('البائع', 'vmp'); ?></th>' +
                           '<th><?php _e('الخطة المطلوبة', 'vmp'); ?></th>' +
                           '<th><?php _e('السعر', 'vmp'); ?></th>' +
                           '<th><?php _e('التاريخ', 'vmp'); ?></th>' +
                           '<th><?php _e('إجراءات', 'vmp'); ?></th>' +
                           '</tr></thead><tbody>';

                    $.each(requests, function(i, req) {
                        var date = new Date(req.created_at);
                        var formattedDate = date.toLocaleDateString('ar-SA');

                        html += '<tr>' +
                                '<td><strong>' + req.store_name + '</strong></td>' +
                                '<td>' + req.plan_name + '</td>' +
                                '<td>' + req.plan_price + '</td>' +
                                '<td>' + formattedDate + '</td>' +
                                '<td>' +
                                '<button class="button button-primary vmp-approve-change" data-id="' + req.id + '">' +
                                '<?php _e('موافقة', 'vmp'); ?>' +
                                '</button> ' +
                                '<button class="button vmp-reject-change" data-id="' + req.id + '">' +
                                '<?php _e('رفض', 'vmp'); ?>' +
                                '</button>' +
                                '</td>' +
                                '</tr>';
                    });

                    html += '</tbody></table>';
                }

                $('#vmp-pending-requests').html(html);
                $('#vmp-pending-count').text(requests.length);
            } else {
                $('#vmp-pending-requests').html('<p style="text-align:center; padding: 20px; color: #94a3b8;"><?php _e('حدث خطأ في تحميل الطلبات.', 'vmp'); ?></p>');
            }
        }).fail(function() {
            $('#vmp-pending-requests').html('<p style="text-align:center; padding: 20px; color: #94a3b8;"><?php _e('خطأ في الاتصال.', 'vmp'); ?></p>');
        });
    }

    // ── تحميل الطلبات عند فتح الصفحة ──
    loadPendingRequests();

    // ── تحديث الطلبات كل 30 ثانية ──
    setInterval(loadPendingRequests, 30000);

    // ── الموافقة على طلب تغيير الخطة ──
    $(document).on('click', '.vmp-approve-change', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var requestId = $btn.data('id');

        if (!confirm('<?php _e('هل أنت متأكد من الموافقة على هذا الطلب؟', 'vmp'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('جاري...', 'vmp'); ?>');

        $.post(ajaxurl, {
            action: 'vmp_admin_approve_plan_change',
            nonce: '<?php echo wp_create_nonce('vmp_admin_nonce'); ?>',
            request_id: requestId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('تمت الموافقة بنجاح', 'vmp'); ?>');
                loadPendingRequests();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ', 'vmp'); ?>');
                $btn.prop('disabled', false).text('<?php _e('موافقة', 'vmp'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>');
            $btn.prop('disabled', false).text('<?php _e('موافقة', 'vmp'); ?>');
        });
    });

    // ── رفض طلب تغيير الخطة ──
    $(document).on('click', '.vmp-reject-change', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var requestId = $btn.data('id');
        var reason = prompt('<?php _e('أدخل سبب الرفض (اختياري):', 'vmp'); ?>');

        if (!confirm('<?php _e('هل أنت متأكد من رفض هذا الطلب؟', 'vmp'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('جاري...', 'vmp'); ?>');

        $.post(ajaxurl, {
            action: 'vmp_admin_reject_plan_change',
            nonce: '<?php echo wp_create_nonce('vmp_admin_nonce'); ?>',
            request_id: requestId,
            reason: reason || ''
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('تم الرفض', 'vmp'); ?>');
                loadPendingRequests();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ', 'vmp'); ?>');
                $btn.prop('disabled', false).text('<?php _e('رفض', 'vmp'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>');
            $btn.prop('disabled', false).text('<?php _e('رفض', 'vmp'); ?>');
        });
    });
});
</script>