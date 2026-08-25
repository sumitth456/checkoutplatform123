<?php

namespace App\Modules\Order\Http\Requests;

use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Validation\Rule;

class SubmitCheckoutRequest extends StoreFunnelOrderRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'payment_token' => ['required', 'string', 'not_regex:/^\d{13,19}$/'],
            'payment_method' => ['nullable', Rule::in(['card', 'ach'])],
            'ach_provider' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'payment_intent_id' => ['nullable', 'string', 'max:255'],
            'metadata.subscription_plan_uuid' => ['nullable', 'uuid', 'exists:subscription_plans,uuid'],
        ]);
    }
}
