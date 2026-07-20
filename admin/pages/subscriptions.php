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

    <?php
    // عرض إشعارات المشرف (من option) ليضمن وصول الإشعارات حتى لو فشل البريد
    $vmp_admin_notices = get_option('vmp_admin_notices', []);
    if (!empty($vmp_admin_notices) && is_array($vmp_admin_notices)) : ?>
        <div class="vmp-admin-notices" style="margin:12px 0;">
            <?php foreach (array_slice($vmp_admin_notices, 0, 20) as $an) :
                $type = isset($an['type']) ? esc_attr($an['type']) : 'info';
                $msg  = isset($an['message']) ? esc_html($an['message']) : '';
                $created = isset($an['created_at']) ? esc_html($an['created_at']) : '';
            ?>
                <div class="notice notice-<?php echo $type; ?>" style="margin-bottom:8px; padding:10px;">
                    <p style="margin:0;"><strong><?php echo $msg; ?></strong> <span style="color:#6b7280; font-size:12px;"><?php echo $created; ?></span></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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

<style>
/* CSS omitted for brevity in this context (kept as original) */
</style>

<!-- expose admin nonce to JS -->
<script>var vmp_admin = { nonce: '<?php echo wp_create_nonce('vmp_admin_nonce'); ?>' };</script>

<!-- JavaScript -->
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

    // ... other JS unchanged until loadPendingRequests

    function loadPendingRequests() {
        $.post(ajaxurl, {
            action: 'vmp_get_pending_plan_changes',
            nonce: vmp_admin.nonce
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
                var msg = (response.data && response.data.message) ? response.data.message : '<?php _e('حدث خطأ في تحميل الطلبات.', 'vmp'); ?>';
                $('#vmp-pending-requests').html('<p style="text-align:center; padding: 20px; color: #94a3b8;">' + msg + '</p>');
            }
        }).fail(function(xhr) {
            var body = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : xhr.responseText || '<?php _e('خطأ في الاتصال.', 'vmp'); ?>';
            $('#vmp-pending-requests').html('<p style="text-align:center; padding: 20px; color: #94a3b8;">' + body + '</p>');
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
            nonce: vmp_admin.nonce,
            request_id: requestId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('تمت الموافقة بنجاح', 'vmp'); ?>');
                loadPendingRequests();
            } else {
                alert(response.data.message || '<?php _e('حدث خطأ', 'vmp'); ?>');
                $btn.prop('disabled', false).text('<?php _e('موافقة', 'vmp'); ?>');
            }
        }).fail(function(xhr) {
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : '<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>';
            alert(msg);
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
            nonce: vmp_admin.nonce,
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
        }).fail(function(xhr) {
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : '<?php _e('حدث خطأ في الاتصال.', 'vmp'); ?>';
            alert(msg);
            $btn.prop('disabled', false).text('<?php _e('رفض', 'vmp'); ?>');
        });
    });
});
</script>
