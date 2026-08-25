<?php

namespace App\Modules\Payment\Http\Requests;

use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_token' => ['required', 'string', 'not_regex:/^\d{13,19}$/'],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'order_id' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
