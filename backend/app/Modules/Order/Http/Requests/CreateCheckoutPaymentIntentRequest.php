<?php

namespace App\Modules\Order\Http\Requests;

class CreateCheckoutPaymentIntentRequest extends StoreFunnelOrderRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);
    }
}
