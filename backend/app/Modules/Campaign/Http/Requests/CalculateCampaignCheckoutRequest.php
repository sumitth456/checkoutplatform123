<?php

namespace App\Modules\Campaign\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateCampaignCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.variant_uuid' => ['nullable', 'uuid', 'exists:variants,uuid'],
            'items.*.product_uuid' => ['nullable', 'uuid', 'exists:products,uuid'],
            'items.*.color' => ['nullable', 'string', 'max:100'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.order_bump_uuid' => ['nullable', 'uuid'],
            'items.*.campaign_product_line_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.country' => ['nullable', 'string', 'size:2'],
        ];
    }
}
