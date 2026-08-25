<?php

namespace App\Modules\Product\Http\Requests;

use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'sort' => ['nullable', 'string', Rule::in(['name', 'slug', 'status', 'type', 'base_price', 'created_at', 'published_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'with_trashed' => ['nullable', 'boolean'],
        ];
    }
}
