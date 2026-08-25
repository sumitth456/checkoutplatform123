<?php

namespace App\Modules\Variant\Http\Requests;

use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variant = $this->route('variant');

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:255', Rule::unique('variants', 'sku')->ignore($variant?->id)],
            'barcode' => ['nullable', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'price' => ['sometimes', 'numeric', 'min:0'],
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
            'status' => ['sometimes', Rule::enum(VariantStatus::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
