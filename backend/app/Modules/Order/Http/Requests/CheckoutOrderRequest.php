<?php

namespace App\Modules\Order\Http\Requests;

use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_token' => ['required', 'string', 'not_regex:/^\d{13,19}$/'],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
