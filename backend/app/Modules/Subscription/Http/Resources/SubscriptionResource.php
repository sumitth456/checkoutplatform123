<?php

namespace App\Modules\Subscription\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'current_cycle_step' => $this->current_cycle_step,
            'retry_count' => $this->retry_count,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'next_bill_date' => $this->next_billing_at?->toIso8601String(),
            'paused_remaining_days' => $this->paused_remaining_days,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => [
                'uuid' => $this->plan->uuid,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
            ]),
        ];
    }
}
