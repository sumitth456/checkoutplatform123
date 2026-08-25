<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Modules\Subscription\Http\Resources\SubscriptionResource;
use App\Modules\Subscription\Services\RebillService;
use App\Modules\Subscription\Services\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptions,
        private readonly RebillService $rebills,
    ) {}

    public function show(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($subscription->load(['plan', 'customer', 'product', 'variant']));
    }

    public function pause(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource(
            $this->subscriptions->pause($subscription)->load(['plan', 'customer']),
        );
    }

    public function resume(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource(
            $this->subscriptions->resume($subscription)->load(['plan', 'customer']),
        );
    }

    public function cancel(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource(
            $this->subscriptions->cancel($subscription)->load(['plan', 'customer']),
        );
    }

    public function retry(Subscription $subscription): JsonResponse
    {
        $this->rebills->charge($subscription);

        return response()->json([
            'data' => new SubscriptionResource($subscription->fresh(['plan', 'customer'])),
        ]);
    }
}
