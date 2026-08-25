<?php

namespace App\Filament\Admin\Resources\Subscriptions\Pages;

use App\Filament\Admin\Resources\Subscriptions\Concerns\LogsSubscriptionAdminActivity;
use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use App\Models\SubscriptionPlan;
use App\Modules\Subscription\Services\SubscriptionEngineService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSubscription extends CreateRecord
{
    use LogsSubscriptionAdminActivity;

    protected static string $resource = SubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);

        $subscription = app(SubscriptionEngineService::class)->enrollFromPlan(
            customerId: (int) $data['customer_id'],
            plan: $plan,
            paymentToken: (string) $data['payment_token'],
            paymentProvider: $data['payment_provider'] ?? null,
            attributes: array_filter([
                'product_id' => $data['product_id'] ?? null,
                'variant_id' => $data['variant_id'] ?? null,
                'payment_gateway_id' => $data['payment_gateway_id'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
            ], fn ($value) => $value !== null),
        );

        $this->record = $subscription;

        return $subscription;
    }

    protected function afterCreate(): void
    {
        $this->logSubscriptionActivity('created', 'Subscription created from admin');
    }
}
