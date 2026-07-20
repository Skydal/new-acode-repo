<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class GetOrderDetailsRequest
 *
 * Description of administrative platform component GetOrderDetailsRequest.
 *
 * @package vendor-marketplace
 */
class GetOrderDetailsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_orders');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_order_id' => ['required', 'integer'],
        ];
    }

    /**
     * Attributes functionality helper.
     *
     * @return array Output payload.
     */
    protected function attributes(): array
    {
        return [
            'vendor_order_id' => __('معرف طلب البائع', 'vmp'),
        ];
    }
}
