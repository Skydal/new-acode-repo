<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من البائع ──
$user_id = get_current_user_id();
if (!$user_id) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('يجب تسجيل الدخول أولاً.', 'vmp') . '</div>';
    return;
}

$vendor_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\VendorRepositoryInterface::class);
$vendor = $vendor_repo->findByUserId($user_id);

if (!$vendor) {
    echo '<div class="vmp-notice vmp-notice-error">' . __('البائع غير موجود.', 'vmp') . '</div>';
    return;
}
if ($vendor->status !== 'approved') {
    echo '<div class="vmp-notice vmp-notice-warning">' . __('حسابك قيد المراجعة أو غير معتمد.', 'vmp') . '</div>';
    return;
}

// ── جلب خطة الاشتراك والميزات ──
$sub_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
$plan_repo = \VMP\Core\Container::getInstance()->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);
$active_sub = $sub_repo->findActiveByVendor((int) $vendor->id);
$plan = $active_sub ? $plan_repo->find((int) $active_sub->plan_id) : null;
$features = $plan ? $plan_repo->getFeatures((int) $plan->id) : [];

$has_social   = !empty($features['social_links']);
$has_video    = !empty($features['product_video']);
$has_address  = !empty($features['store_address']);

$logo_url   = $vendor->store_logo ? wp_get_attachment_url($vendor->store_logo) : '';
$banner_url = $vendor->store_banner ? wp_get_attachment_url($vendor->store_banner) : '';

$user = wp_get_current_user();
$nav_file = VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php';
?>

<div class="vmp-wrap">
    <!-- التنقل -->
    <?php if (file_exists($nav_file)) include $nav_file; ?>

    <div class="vmp-card" style="max-width: 820px; margin: 0 auto;">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php _e('إعدادات المتجر', 'vmp'); ?></h2>
            <span class="vmp-badge-status vmp-status-approved"><?php _e('نشط', 'vmp'); ?></span>
        </div>

        <form id="vmp-profile-form" class="vmp-ajax-form" data-action="vmp_vendor_update_profile">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vmp_public_nonce'); ?>">

            <!-- قسم: معلومات المتجر الأساسية -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">🏪</span>
                <?php _e('معلومات المتجر', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('اسم المتجر', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="store_name" class="vmp-input" value="<?php echo esc_attr($vendor->store_name ?? ''); ?>" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('رقم الهاتف', 'vmp'); ?></label>
                    <input type="tel" name="phone" class="vmp-input" value="<?php echo esc_attr($vendor->store_phone ?? ''); ?>" dir="ltr" placeholder="+966 500 000 000">
                </div>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('وصف المتجر', 'vmp'); ?></label>
                <textarea name="description" class="vmp-textarea" rows="4"><?php echo esc_textarea($vendor->store_description ?? ''); ?></textarea>
                <div class="vmp-input-hint"><?php _e('نبذة مختصرة تظهر في صفحة متجرك.', 'vmp'); ?></div>
            </div>

            <!-- قسم: الميزات الإضافية (حسب الخطة) -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">✨</span>
                <?php _e('ميزات متقدمة', 'vmp'); ?>
                <span class="vmp-badge-plan"><?php echo $plan ? esc_html($plan->name) : __('مجاني', 'vmp'); ?></span>
            </div>

            <?php if ($has_address) : ?>
                <div class="vmp-form-group">
                    <label><?php _e('عنوان المتجر', 'vmp'); ?></label>
                    <input type="text" name="store_address" class="vmp-input" value="<?php echo esc_attr($vendor->store_address ?? ''); ?>" placeholder="<?php _e('مثال: صنعاء، شارع التعاون', 'vmp'); ?>">
                    <div class="vmp-input-hint">📍 <?php _e('سيظهر العنوان مع خريطة في صفحة متجرك.', 'vmp'); ?></div>
                </div>
            <?php else : ?>
                <div class="vmp-notice vmp-notice-info"><strong>🔒</strong> <?php _e('ميزة العنوان متاحة في الخطط المدفوعة. قم بترقية خطتك.', 'vmp'); ?></div>
            <?php endif; ?>

            <?php if ($has_social) : ?>
                <div class="vmp-form-group">
                    <label><?php _e('روابط التواصل الاجتماعي', 'vmp'); ?></label>
                    <div class="vmp-form-row">
                        <input type="url" name="social_facebook" class="vmp-input" value="<?php echo esc_url($vendor->social_facebook ?? ''); ?>" placeholder="🔵 Facebook">
                        <input type="url" name="social_instagram" class="vmp-input" value="<?php echo esc_url($vendor->social_instagram ?? ''); ?>" placeholder="🟣 Instagram">
                    </div>
                    <div class="vmp-form-row" style="margin-top:10px;">
                        <input type="url" name="social_twitter" class="vmp-input" value="<?php echo esc_url($vendor->social_twitter ?? ''); ?>" placeholder="🐦 Twitter">
                        <input type="url" name="social_youtube" class="vmp-input" value="<?php echo esc_url($vendor->social_youtube ?? ''); ?>" placeholder="▶️ YouTube">
                    </div>
                </div>
            <?php else : ?>
                <div class="vmp-notice vmp-notice-info"><strong>🔒</strong> <?php _e('ميزة روابط التواصل متاحة في الخطط المدفوعة.', 'vmp'); ?></div>
            <?php endif; ?>

            <?php if ($has_video) : ?>
                <div class="vmp-form-group">
                    <label><?php _e('فيديو تعريفي', 'vmp'); ?></label>
                    <input type="url" name="store_video" class="vmp-input" value="<?php echo esc_url($vendor->store_video ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
                    <div class="vmp-input-hint">🎬 <?php _e('رابط YouTube أو Vimeo سيظهر في متجرك.', 'vmp'); ?></div>
                </div>
            <?php else : ?>
                <div class="vmp-notice vmp-notice-info"><strong>🔒</strong> <?php _e('ميزة الفيديو متاحة في الخطط المدفوعة.', 'vmp'); ?></div>
            <?php endif; ?>

            <!-- قسم: الصور -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">🖼️</span>
                <?php _e('صور المتجر', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('شعار المتجر', 'vmp'); ?></label>
                    <div class="vmp-image-upload">
                        <input type="hidden" name="logo_id" value="<?php echo esc_attr($vendor->store_logo ?? 0); ?>">
                        <?php if (!empty($logo_url)) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" class="vmp-image-preview show" alt="Logo">
                        <?php else : ?>
                            <img src="" class="vmp-image-preview" alt="Logo">
                        <?php endif; ?>
                        <div class="upload-icon" style="<?php echo !empty($logo_url) ? 'display:none;' : ''; ?>">📸</div>
                        <p style="<?php echo !empty($logo_url) ? 'display:none;' : ''; ?>"><?php _e('انقر لاختيار صورة', 'vmp'); ?></p>
                    </div>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('غلاف المتجر', 'vmp'); ?></label>
                    <div class="vmp-image-upload">
                        <input type="hidden" name="banner_id" value="<?php echo esc_attr($vendor->store_banner ?? 0); ?>">
                        <?php if (!empty($banner_url)) : ?>
                            <img src="<?php echo esc_url($banner_url); ?>" class="vmp-image-preview show" alt="Banner">
                        <?php else : ?>
                            <img src="" class="vmp-image-preview" alt="Banner">
                        <?php endif; ?>
                        <div class="upload-icon" style="<?php echo !empty($banner_url) ? 'display:none;' : ''; ?>">🖼️</div>
                        <p style="<?php echo !empty($banner_url) ? 'display:none;' : ''; ?>"><?php _e('انقر لاختيار غلاف (يفضل 1200x400)', 'vmp'); ?></p>
                    </div>
                </div>
            </div>

            <!-- قسم: الحساب -->
            <div class="vmp-section-title">
                <span class="vmp-section-icon">👤</span>
                <?php _e('بيانات الحساب', 'vmp'); ?>
            </div>

            <div class="vmp-form-row">
                <div class="vmp-form-group">
                    <label><?php _e('الاسم الأول', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="first_name" class="vmp-input" value="<?php echo esc_attr($user->first_name ?? ''); ?>" required>
                </div>
                <div class="vmp-form-group">
                    <label><?php _e('الاسم الأخير', 'vmp'); ?> <span class="required">*</span></label>
                    <input type="text" name="last_name" class="vmp-input" value="<?php echo esc_attr($user->last_name ?? ''); ?>" required>
                </div>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('البريد الإلكتروني', 'vmp'); ?> <span class="required">*</span></label>
                <input type="email" name="user_email" class="vmp-input" value="<?php echo esc_attr($user->user_email ?? ''); ?>" required>
            </div>

            <div class="vmp-form-group">
                <label><?php _e('كلمة المرور الجديدة', 'vmp'); ?></label>
                <input type="password" name="password" class="vmp-input" placeholder="••••••••">
                <div class="vmp-input-hint">🔑 <?php _e('اتركه فارغاً إذا لم ترغب في التغيير.', 'vmp'); ?></div>
            </div>

            <div style="margin-top:30px;">
                <button type="submit" class="vmp-btn vmp-btn-primary vmp-btn-lg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('حفظ التعديلات', 'vmp'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="vmp-loading"><div class="vmp-spinner"></div></div>

<style>
.vmp-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 17px;
    font-weight: 700;
    color: var(--vmp-text);
    margin: 28px 0 18px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--vmp-border);
}
.vmp-section-icon { font-size: 20px; }
.vmp-badge-plan {
    margin-right: auto;
    background: var(--vmp-primary-light);
    color: var(--vmp-primary);
    padding: 2px 14px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
}
.vmp-notice-info strong { font-size: 14px; }
</style>