<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\DTO\RegisterVendorDTO;
use VMP\Core\Container;
use VMP\Contracts\VendorRepositoryInterface;

/**
 * Class RegisterVendorRequest
 *
 * Description of administrative platform component RegisterVendorRequest.
 *
 * @package vendor-marketplace
 */
class RegisterVendorRequest extends AbstractRequest
{
    /**
     * تحويل بيانات الطلب إلى DTO
     */
    public function toDTO(): RegisterVendorDTO
    {
        return RegisterVendorDTO::fromArray($this->validated());
    }
    /**
     * تعريف القواعد الأساسية
     */
    protected function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'min:3', 'max:100'],
            'user_email' => ['required', 'email'],
            'store_slug' => ['string'],
            'phone'      => ['string', 'phone'],
            'first_name' => ['string'],
            'last_name'  => ['string'],
            'user_pass'  => ['string', 'min:6'],
        ];
    }

    /**
     * أسماء الحقول المخصصة لرسائل الخطأ
     */
    protected function attributes(): array
    {
        return [
            'store_name' => __('اسم المتجر', 'vmp'),
            'user_email' => __('البريد الإلكتروني', 'vmp'),
            'store_slug' => __('الرابط المختصر', 'vmp'),
            'phone'      => __('رقم الهاتف', 'vmp'),
            'first_name' => __('الاسم الأول', 'vmp'),
            'last_name'  => __('الاسم الأخير', 'vmp'),
            'user_pass'  => __('كلمة المرور', 'vmp'),
        ];
    }

    /**
     * رسائل مخصصة
     */
    protected function messages(): array
    {
        return [
            'store_name.regex' => __('اسم المتجر يحتوي على أحرف غير مسموحة.', 'vmp'),
            'store_slug.regex' => __('الرابط المختصر (slug) يجب أن يحتوي على أحرف وأرقام وشرطات فقط.', 'vmp'),
        ];
    }

    /**
     * تنفيذ التحققات الإضافية المعقدة (مثل فحص قاعدة البيانات)
     */
    public function validate(): bool
    {
        // 1. التحقق الأساسي بناءً على rules()
        if (!parent::validate()) {
            return false;
        }

        $errors = [];

        // 2. تحقق من أن اسم المتجر لا يحتوي على رموز غير مسموحة
        $storeName = $this->string('store_name');
        if (!preg_match('/^[\p{L}\s\-0-9]+$/u', $storeName)) {
            $errors[] = $this->messages()['store_name.regex'];
        }

        // 3. تحقق من البريد الإلكتروني إذا كان مسجلاً مسبقاً
        $email = $this->string('user_email');
        if (email_exists($email)) {
            $errors[] = __('هذا البريد الإلكتروني مسجّل مسبقاً.', 'vmp');
        }

        // 4. تحقق من أن الـ slug لا يحتوي إلا على أحرف مناسبة
        $slug = $this->string('store_slug') ?: sanitize_title($storeName);
        if ($slug && !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors[] = $this->messages()['store_slug.regex'];
        }

        // 5. التحقق من عدم تكرار الـ slug باستخدام VendorRepositoryInterface
        if ($slug) {
            /** @var VendorRepositoryInterface $vendorRepo */
            $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
            if ($vendorRepo && $vendorRepo->slugExists($slug)) {
                $errors[] = __('الرابط المختصر مستخدم مسبقاً.', 'vmp');
            }
        }

        // 6. التحقق من الأسماء (الأول والأخير)
        $firstName = $this->string('first_name');
        if ($firstName && !preg_match('/^[\p{L}\s\-]+$/u', $firstName)) {
            $errors[] = __('الاسم الأول يجب أن يحتوي على أحرف فقط.', 'vmp');
        }

        $lastName = $this->string('last_name');
        if ($lastName && !preg_match('/^[\p{L}\s\-]+$/u', $lastName)) {
            $errors[] = __('الاسم الأخير يجب أن يحتوي على أحرف فقط.', 'vmp');
        }

        if (!empty($errors)) {
            // إضافة الأخطاء المخصصة إلى مصفوفة الأخطاء
            $reflection = new \ReflectionClass(parent::class);
            $property = $reflection->getProperty('errors');
            $property->setAccessible(true);
            $existingErrors = $property->getValue($this);
            $property->setValue($this, array_merge($existingErrors, $errors));
            
            return false;
        }

        return true;
    }
}
