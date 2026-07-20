<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\VendorService;
use VMP\Http\Requests\RegisterVendorRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;
use VMP\Http\Responses\ApiResponse;
use VMP\Exceptions\ServiceException;

/**
 * Class VendorController
 *
 * Description of administrative platform component VendorController.
 *
 * @package vendor-marketplace
 */
class VendorController extends BaseController
{
    public function __construct(
        private VendorService $vendorService
    ) {}

    /**
     * تسجيل بائع جديد
     */
    public function registerVendor(RegisterVendorRequest $request): ApiResponse
    {
        try {
            // 1. تحويل الـ Request إلى DTO
            $dto = $request->toDTO();

            // 2. معالجة العمليات عبر طبقة الخدمة
            $vendor = $this->vendorService->registerVendor($dto);

            // 3. إعداد بيانات الاستجابة
            $vendorArray = [];
            if ($vendor instanceof \VMP\DTO\VendorDTO) {
                $vendorArray = $vendor->toArray();
            } elseif (is_array($vendor)) {
                $vendorArray = $vendor;
            }

            // 4. إرجاع استجابة ناجحة مع redirect إلى لوحة البائع
            return new SuccessResponse(
                data: array_merge($vendorArray, ['redirect' => home_url('/vendor-dashboard/')]),
                message: __('تم تقديم طلب التسجيل بنجاح، يرجى الانتظار لحين المراجعة.', 'vmp')
            );

        } catch (ServiceException $e) {
            $msg = $e->getMessage();

            // حالة: المستخدم لديه حساب بائع موجود مسبقاً
            if (mb_stripos($msg, 'حساب بائع') !== false || mb_stripos($msg, 'لديك حساب بائع') !== false) {
                return new SuccessResponse(
                    data: ['redirect' => home_url('/vendor-dashboard/')],
                    message: __('لديك حساب بائع مسجّل مسبقاً — جاري تحويلك إلى لوحة البائع.', 'vmp')
                );
            }

            return new ErrorResponse(
                message: $msg,
                additionalData: ['message' => $msg],
                statusCode: 400
            );
        } catch (\Throwable $e) {
            // Unexpected errors
            error_log('[VMP][VendorController] Unexpected error during registerVendor: ' . $e->getMessage());
            return new ErrorResponse(
                message: __('حدث خطأ أثناء معالجة الطلب.', 'vmp'),
                additionalData: ['error' => $e->getMessage()],
                statusCode: 500
            );
        }
    }

    /**
     * تحديث الملف الشخصي للبائع
     * 
     * @todo إنشاء UpdateVendorProfileRequest وتمريره هنا لاحقاً
     */
    public function updateProfile(): ApiResponse
    {
        // مجرد Placeholder لنطبق نمط التسجيل على باقي الدوال في المرحلة القادمة
        return new SuccessResponse(message: 'Coming soon');
    }

    /**
     * الموافقة على بائع من قبل المشرف
     * 
     * @todo إنشاء AdminApproveVendorRequest وتمريره هنا لاحقاً
     */
    public function adminApprove(): ApiResponse
    {
        // مجرد Placeholder
        return new SuccessResponse(message: 'Coming soon');
    }

    /**
     * رفض بائع من قبل المشرف
     * 
     * @todo إنشاء AdminRejectVendorRequest وتمريره هنا لاحقاً
     */
    public function adminReject(): ApiResponse
    {
        // مجرد Placeholder
        return new SuccessResponse(message: 'Coming soon');
    }
}
