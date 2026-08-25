<?php

namespace App\Models;

use App\Modules\Subscription\Enums\BillingStepType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanStep extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'step_order',
        'billing_type',
        'cycle_type',
        'days_from_start',
        'interval_days',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'billing_type' => BillingStepType::class,
            'cycle_type' => \App\Modules\Subscription\Enums\BillingCycleType::class,
            'amount' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
