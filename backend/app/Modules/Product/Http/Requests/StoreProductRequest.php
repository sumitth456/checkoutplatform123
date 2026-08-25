<?php

namespace App\Modules\Product\Http\Requests;

use App\Modules\Product\Enums\BillingInterval;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'currency' => ['nullable', 'string', 'size:3'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'requires_shipping' => ['nullable', 'boolean'],
            'billing_interval' => ['nullable', Rule::enum(BillingInterval::class), 'required_if:type,subscription'],
            'billing_interval_count' => ['nullable', 'integer', 'min:1', 'required_if:type,subscription'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'metadata' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
