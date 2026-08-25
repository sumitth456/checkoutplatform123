<?php

namespace App\Modules\Variant\Http\Requests;

use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:variants,sku'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'inventory_policy' => ['nullable', Rule::enum(InventoryPolicy::class)],
            'track_inventory' => ['nullable', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'options' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(VariantStatus::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
