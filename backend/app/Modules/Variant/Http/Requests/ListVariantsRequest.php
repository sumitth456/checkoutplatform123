<?php

namespace App\Modules\Variant\Http\Requests;

use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(VariantStatus::class)],
            'sort' => ['nullable', 'string', Rule::in(['name', 'sku', 'price', 'status', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'with_trashed' => ['nullable', 'boolean'],
        ];
    }
}
