<?php

namespace App\Modules\Order\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionCheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'next_bill_date' => $this->next_billing_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => [
                'uuid' => $this->plan->uuid,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
            ]),
        ];
    }
}
