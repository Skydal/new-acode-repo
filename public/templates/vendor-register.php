<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── استخدام الحاوية للحصول على المستودعات ──
$container = \VMP\Core\Container::getInstance();
$plan_repo = $container->make(\VMP\Repositories\SubscriptionPlanRepository::class);

// ── جلب خطط الاشتراك النشطة ──
$plans = $plan_repo->getAll(true);

// ── التحقق من وجود خطة مجانية كافتراضية ──
$default_plan_id = 0;
foreach ($plans as $plan) {
    if ((float) $plan->price == 0) {
        $default_plan_id = (int) $plan->id;
        break;
    }
}
if ($default_plan_id === 0 && !empty($plans)) {
    $default_plan_id = (int) $plans[0]->id;
}
?>

<div class="vmp-wrap">
    <div class="vmp-container" style="max-width: 800px;">
        
        <div class="vmp-header-bar" style="text-align: center;">
            <h1><?php _e('انضم إلينا كبائع', 'vmp'); ?></h1>
            <p><?php _e('قم بإنشاء متجرك الخاص وابدأ البيع في دقائق.', 'vmp'); ?></p>
        </div>

        <div class="vmp-card">
            <!-- مسار التقدم -->
            <div class="vmp-steps">
                <div class="vmp-step active">
                    <div class="vmp-step-num">1</div>
                    <span><?php _e('البيانات الأساسية', 'vmp'); ?></span>
                </div>
                <div class="vmp-step-line"></div>
                <div class="vmp-step">
                    <div class="vmp-step-num">2</div>
                    <span><?php _e('بيانات المتجر', 'vmp'); ?></span>
                </div>
                <div class="vmp-step-line"></div>
                <div class="vmp-step">
                    <div class="vmp-step-num">3</div>
                    <span><?php _e('خطة الاشتراك', 'vmp'); ?></span>
                </div>
            </div>

            <form id="vmp-register-form" class="vmp-ajax-form vmp-reset-on-success" data-action="vmp_vendor_register">
                <?php 
                // ✅ تغيير الـ nonce إلى vmp_vendor_register_nonce
                wp_nonce_field('vmp_vendor_register_nonce', 'nonce'); 
                ?>
                
                <!-- الخطوة 1: البيانات الأساسية -->
                <div class="vmp-step-content active">
                    <div class="vmp-form-row">
                        <div class="vmp-form-group">
                            <label><?php _e('الاسم الأول', 'vmp'); ?> <span class="required">*</span></label>
                            <input type="text" name="first_name" class="vmp-input" required>
                        </div>
                        <div class="vmp-form-group">
                            <label><?php _e('الاسم الأخير', 'vmp'); ?> <span class="required">*</span></label>
                            <input type="text" name="last_name" class="vmp-input" required>
                        </div>
                    </div>
                    <div class="vmp-form-group">
                        <label><?php _e('البريد الإلكتروني', 'vmp'); ?> <span class="required">*</span></label>
                        <input type="email" name="user_email" class="vmp-input" required>
                    </div>
                    <div class="vmp-form-group">
                        <label><?php _e('كلمة المرور', 'vmp'); ?> <span class="required">*</span></label>
                        <input type="password" name="user_pass" class="vmp-input" required minlength="6">
                        <div class="vmp-input-hint"><?php _e('يجب أن تكون كلمة المرور 6 أحرف على الأقل.', 'vmp'); ?></div>
                    </div>
                    
                    <div style="text-align: left; margin-top: 24px;">
                        <button type="button" class="vmp-btn vmp-btn-primary vmp-btn-next"><?php _e('التالي', 'vmp'); ?> &larr;</button>
                    </div>
                </div>

                <!-- الخطوة 2: بيانات المتجر -->
                <div class="vmp-step-content">
                    <div class="vmp-form-group">
                        <label><?php _e('اسم المتجر', 'vmp'); ?> <span class="required">*</span></label>
                        <input type="text" name="store_name" class="vmp-input" required>
                        <div class="vmp-input-hint"><?php _e('سيظهر هذا الاسم للعملاء.', 'vmp'); ?></div>
                    </div>
                    <div class="vmp-form-group">
                        <label><?php _e('رابط المتجر', 'vmp'); ?> <span class="required">*</span></label>
                        <div style="display:flex; align-items:center; direction:ltr;">
                            <span style="background:var(--vmp-border); padding:11px 14px; border-radius:6px 0 0 6px; font-size:13px; color:var(--vmp-text-muted); border:1.5px solid var(--vmp-border); border-right:none;"><?php echo home_url('/store/'); ?></span>
                            <input type="text" name="store_slug" class="vmp-input" required style="border-radius:0 6px 6px 0; direction:ltr;">
                        </div>
                        <div class="vmp-input-hint"><?php _e('استخدم أحرفاً وأرقاماً فقط بدون مسافات.', 'vmp'); ?></div>
                    </div>
                    <div class="vmp-form-group">
                        <label><?php _e('رقم الهاتف (للتواصل عبر واتساب)', 'vmp'); ?> <span class="required">*</span></label>
                        <input type="tel" name="phone" class="vmp-input" required dir="ltr" placeholder="+966500000000">
                        <div class="vmp-input-hint"><?php _e('ادخل رقم الهاتف مع رمز الدولة.', 'vmp'); ?></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                        <button type="button" class="vmp-btn vmp-btn-outline vmp-btn-prev">&rarr; <?php _e('السابق', 'vmp'); ?></button>
                        <button type="button" class="vmp-btn vmp-btn-primary vmp-btn-next"><?php _e('التالي', 'vmp'); ?> &larr;</button>
                    </div>
                </div>

                <!-- الخطوة 3: خطة الاشتراك -->
                <div class="vmp-step-content">
                    <?php if (empty($plans)) : ?>
                        <div class="vmp-notice vmp-notice-info">
                            <?php _e('يمكنك المتابعة والتسجيل كبائع بالخطة الافتراضية حالياً.', 'vmp'); ?>
                        </div>
                        <input type="hidden" name="plan_id" value="0">
                    <?php else : ?>
                        <div class="vmp-plans-grid">
                            <?php foreach ($plans as $i => $plan) : 
                                // تحويل المميزات من JSON إلى مصفوفة
                                $features = is_string($plan->features) 
                                    ? json_decode($plan->features, true) 
                                    : (is_array($plan->features) ? $plan->features : []);
                                
                                // استخراج أسماء المميزات المفعلة فقط
                                $active_features = [];
                                if (is_array($features)) {
                                    foreach ($features as $key => $value) {
                                        if ($value === true || $value === 1 || $value === '1') {
                                            $active_features[] = $key;
                                        }
                                    }
                                }

                                // تسميات المميزات العربية
                                $feature_labels = [
                                    'whatsapp_button' => __('طلب عبر واتساب', 'vmp'),
                                    'store_address' => __('عنوان المتجر مع خريطة', 'vmp'),
                                    'social_links' => __('روابط التواصل الاجتماعي', 'vmp'),
                                    'product_video' => __('فيديو تعريفي', 'vmp'),
                                    'unlimited_products' => __('منتجات غير محدودة', 'vmp'),
                                    'custom_domain' => __('نطاق مخصص', 'vmp'),
                                    'advanced_analytics' => __('تحليلات متقدمة', 'vmp'),
                                    'coupons' => __('كوبونات خصم', 'vmp'),
                                    'trusted_badge' => __('شارة موثوق', 'vmp'),
                                    'priority_support' => __('دعم أولوية', 'vmp'),
                                ];

                                $is_free = ((float) $plan->price == 0);
                                $is_default = ($default_plan_id === (int) $plan->id);
                            ?>
                                <div class="vmp-plan-card <?php echo $is_default ? 'selected featured' : ''; ?>">
                                    <?php if ($is_free) : ?>
                                        <div class="vmp-plan-badge" style="background:#22c55e;"><?php _e('مجاني', 'vmp'); ?></div>
                                    <?php elseif ($is_default) : ?>
                                        <div class="vmp-plan-badge"><?php _e('موصى به', 'vmp'); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="vmp-plan-name"><?php echo esc_html($plan->name); ?></div>
                                    <div class="vmp-plan-price">
                                        <?php if ($is_free) : ?>
                                            <span style="font-size:24px;"><?php _e('مجاني', 'vmp'); ?></span>
                                        <?php else : ?>
                                            <?php echo wc_price($plan->price); ?>
                                            <small>/ <?php echo $plan->billing_period === 'month' ? __('شهر', 'vmp') : __('سنة', 'vmp'); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <ul class="vmp-plan-features">
                                        <li><span class="check">✔</span> <?php printf(__('عمولة الموقع: %s%%', 'vmp'), esc_html($plan->commission_rate)); ?></li>
                                        <li><span class="check">✔</span> <?php printf(__('المنتجات: %s', 'vmp'), $plan->max_products > 0 ? esc_html($plan->max_products) : __('غير محدود', 'vmp')); ?></li>
                                        <?php foreach ($active_features as $feature_key) : ?>
                                            <li><span class="check">✔</span> <?php echo esc_html($feature_labels[$feature_key] ?? ucfirst(str_replace('_', ' ', $feature_key))); ?></li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <label class="vmp-btn vmp-btn-block <?php echo $is_default ? 'vmp-btn-primary' : 'vmp-btn-outline'; ?>" style="cursor:pointer;">
                                        <input type="radio" name="plan_id" value="<?php echo $plan->id; ?>" <?php checked($is_default); ?> style="display:none;" onchange="
                                            document.querySelectorAll('.vmp-plan-card').forEach(c => { c.classList.remove('selected'); c.querySelector('.vmp-btn').className = 'vmp-btn vmp-btn-block vmp-btn-outline'; });
                                            this.closest('.vmp-plan-card').classList.add('selected');
                                            this.closest('.vmp-btn').className = 'vmp-btn vmp-btn-block vmp-btn-primary';
                                        ">
                                        <?php _e('اختيار الخطة', 'vmp'); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; margin-top: 24px; border-top: 1px solid var(--vmp-border); padding-top: 20px;">
                        <button type="button" class="vmp-btn vmp-btn-outline vmp-btn-prev">&rarr; <?php _e('السابق', 'vmp'); ?></button>
                        <button type="submit" class="vmp-btn vmp-btn-success">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;display:inline-block;vertical-align:middle;margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <?php _e('إنشاء حساب المتجر', 'vmp'); ?>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<div class="vmp-loading"><div class="vmp-spinner"></div></div>