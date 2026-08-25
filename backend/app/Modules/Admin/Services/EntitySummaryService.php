<?php

namespace App\Modules\Admin\Services;

use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

final class EntitySummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function forRecord(Model $record): array
    {
        return match (true) {
            $record instanceof Order => $this->forOrder($record),
            $record instanceof Customer => $this->forCustomer($record),
            $record instanceof Subscription => $this->forSubscription($record),
            $record instanceof Transaction => $this->forTransaction($record),
            $record instanceof User => $this->forUser($record),
            default => $this->generic($record),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function forOrder(Order $order): array
    {
        $order->loadMissing(['customer', 'paymentGateway', 'subscriptions.plan']);
        $subscription = $order->subscriptions->first();

        return [
            'title' => $order->order_number,
            'subtitle' => $order->customer?->full_name ?? $order->customer_email,
            'status' => [
                'label' => str($order->status->value ?? $order->status)->headline(),
                'class' => $this->orderStatusClass($order),
            ],
            'current_step' => $this->orderCurrentStep($order),
            'next_step' => $subscription?->next_billing_at
                ? 'Rebill on '.$subscription->next_billing_at->format('M j, Y')
                : ($order->fulfillment_status?->value === 'fulfilled' ? 'Complete' : 'Fulfillment pending'),
            'progress' => $this->orderProgress($order, $subscription),
            'metrics' => array_values(array_filter([
                ['label' => 'Customer', 'value' => $order->customer?->full_name ?? $order->customer_email, 'url' => $order->customer ? CustomerResource::getUrl('view', ['record' => $order->customer]) : null],
                ['label' => 'Email', 'value' => $order->customer_email],
                ['label' => 'Gateway', 'value' => $order->paymentGateway?->name ?? '—'],
                ['label' => 'Total', 'value' => number_format((float) $order->grand_total, 2).' '.strtoupper((string) $order->currency)],
                ['label' => 'Payment', 'value' => str($order->payment_status->value ?? $order->payment_status)->headline()],
                ['label' => 'Subscription', 'value' => $subscription?->uuid, 'url' => $subscription ? SubscriptionResource::getUrl('view', ['record' => $subscription]) : null],
            ], fn (array $metric): bool => filled($metric['value'] ?? null))),
            'created_at' => $order->placed_at?->format('M j, Y H:i') ?? $order->created_at?->format('M j, Y H:i'),
            'updated_at' => $order->updated_at?->format('M j, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forCustomer(Customer $customer): array
    {
        return [
            'title' => $customer->full_name ?: $customer->email,
            'subtitle' => $customer->email,
            'status' => [
                'label' => str($customer->status ?? 'active')->headline(),
                'class' => 'cp-badge--info',
            ],
            'current_step' => $customer->last_order_at
                ? 'Last order '.$customer->last_order_at->format('M j, Y')
                : 'No orders yet',
            'next_step' => ($customer->orders_count ?? 0).' orders · '.number_format((float) $customer->total_spent, 2).' spent',
            'progress' => [
                ['label' => 'Created', 'status' => 'completed'],
                ['label' => 'Orders', 'status' => ($customer->orders_count ?? 0) > 0 ? 'completed' : 'pending'],
                ['label' => 'Active', 'status' => 'current'],
            ],
            'metrics' => [
                ['label' => 'Phone', 'value' => $customer->phone ?? '—'],
                ['label' => 'Orders', 'value' => (string) ($customer->orders_count ?? 0)],
                ['label' => 'Total spent', 'value' => number_format((float) $customer->total_spent, 2).' '.strtoupper((string) ($customer->currency ?? 'USD'))],
                ['label' => 'Currency', 'value' => strtoupper((string) ($customer->currency ?? 'USD'))],
            ],
            'created_at' => $customer->created_at?->format('M j, Y H:i'),
            'updated_at' => $customer->updated_at?->format('M j, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing(['customer', 'plan', 'product', 'paymentGateway']);

        return [
            'title' => $subscription->plan?->name ?? 'Subscription',
            'subtitle' => $subscription->customer?->email,
            'status' => [
                'label' => str($subscription->status->value ?? $subscription->status)->headline(),
                'class' => 'cp-badge--info',
            ],
            'current_step' => 'Cycle '.($subscription->current_cycle_step ?? 1),
            'next_step' => $subscription->next_billing_at
                ? 'Next charge '.$subscription->next_billing_at->format('M j, Y H:i')
                : 'No upcoming charge',
            'progress' => [
                ['label' => 'Created', 'status' => 'completed'],
                ['label' => 'Billing', 'status' => 'current'],
                ['label' => 'Renewal', 'status' => 'pending'],
            ],
            'metrics' => array_values(array_filter([
                ['label' => 'Customer', 'value' => $subscription->customer?->full_name ?? $subscription->customer?->email, 'url' => $subscription->customer ? CustomerResource::getUrl('view', ['record' => $subscription->customer]) : null],
                ['label' => 'Product', 'value' => $subscription->product?->name],
                ['label' => 'Amount', 'value' => number_format((float) $subscription->amount, 2).' '.strtoupper((string) $subscription->currency)],
                ['label' => 'Gateway', 'value' => $subscription->paymentGateway?->name ?? '—'],
                ['label' => 'Retries', 'value' => (string) ($subscription->retry_count ?? 0)],
            ], fn (array $metric): bool => filled($metric['value'] ?? null))),
            'created_at' => $subscription->started_at?->format('M j, Y H:i') ?? $subscription->created_at?->format('M j, Y H:i'),
            'updated_at' => $subscription->updated_at?->format('M j, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forTransaction(Transaction $transaction): array
    {
        $transaction->loadMissing(['customer', 'order', 'paymentGateway']);

        return [
            'title' => str($transaction->type)->headline().' · '.number_format((float) $transaction->amount, 2).' '.strtoupper((string) $transaction->currency),
            'subtitle' => $transaction->customer?->email ?? $transaction->order?->order_number,
            'status' => [
                'label' => str($transaction->status)->headline(),
                'class' => in_array($transaction->status, ['failed', 'declined'], true) ? 'cp-badge--danger' : 'cp-badge--success',
            ],
            'current_step' => str($transaction->type)->headline(),
            'next_step' => $transaction->processed_at?->format('M j, Y H:i') ?? 'Pending processing',
            'progress' => [
                ['label' => 'Initiated', 'status' => 'completed'],
                ['label' => str($transaction->type)->headline(), 'status' => 'current'],
                ['label' => 'Settled', 'status' => in_array($transaction->status, ['success', 'captured', 'succeeded'], true) ? 'completed' : 'pending'],
            ],
            'metrics' => [
                ['label' => 'Order', 'value' => $transaction->order?->order_number, 'url' => $transaction->order ? OrderResource::getUrl('view', ['record' => $transaction->order]) : null],
                ['label' => 'Gateway', 'value' => $transaction->paymentGateway?->name ?? '—'],
                ['label' => 'External ID', 'value' => $transaction->external_id ?? '—'],
            ],
            'created_at' => $transaction->created_at?->format('M j, Y H:i'),
            'updated_at' => $transaction->updated_at?->format('M j, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forUser(User $user): array
    {
        return [
            'title' => $user->name,
            'subtitle' => $user->email,
            'status' => [
                'label' => str($user->status ?? 'active')->headline(),
                'class' => ($user->status ?? 'active') === 'active' ? 'cp-badge--success' : 'cp-badge--danger',
            ],
            'current_step' => $user->last_login_at ? 'Last login '.$user->last_login_at->format('M j, Y') : 'Never logged in',
            'next_step' => 'Role: '.($user->roles->first()?->name ?? '—'),
            'progress' => [
                ['label' => 'Created', 'status' => 'completed'],
                ['label' => 'Access', 'status' => 'current'],
            ],
            'metrics' => [
                ['label' => 'Email', 'value' => $user->email],
            ],
            'created_at' => $user->created_at?->format('M j, Y H:i'),
            'updated_at' => $user->updated_at?->format('M j, Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generic(Model $record): array
    {
        $title = method_exists($record, 'getFilamentName')
            ? $record->getFilamentName()
            : ($record->name ?? $record->uuid ?? class_basename($record));

        return [
            'title' => (string) $title,
            'subtitle' => class_basename($record),
            'status' => ['label' => 'Active', 'class' => 'cp-badge--muted'],
            'current_step' => '—',
            'next_step' => '—',
            'progress' => [],
            'metrics' => [],
            'created_at' => $record->created_at?->format('M j, Y H:i'),
            'updated_at' => $record->updated_at?->format('M j, Y H:i'),
        ];
    }

    private function orderCurrentStep(Order $order): string
    {
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return 'Payment captured';
        }

        if ($order->payment_status === OrderPaymentStatus::Pending) {
            return 'Awaiting payment';
        }

        return str($order->payment_status->value ?? $order->payment_status)->headline();
    }

    private function orderStatusClass(Order $order): string
    {
        if ($order->status === OrderStatus::Cancelled) {
            return 'cp-badge--danger';
        }

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return 'cp-badge--success';
        }

        return 'cp-badge--warning';
    }

    /**
     * @return list<array{label: string, status: string}>
     */
    private function orderProgress(Order $order, ?Subscription $subscription): array
    {
        $steps = [
            ['label' => 'Created', 'status' => $order->placed_at ? 'completed' : 'pending'],
            ['label' => 'Paid', 'status' => $order->paid_at ? 'completed' : ($order->payment_status === OrderPaymentStatus::Pending ? 'current' : 'failed')],
        ];

        if ($subscription) {
            $steps[] = ['label' => 'Subscription', 'status' => 'completed'];
            $steps[] = ['label' => 'Rebill', 'status' => $subscription->next_billing_at?->isFuture() ? 'pending' : 'current'];
        }

        $steps[] = ['label' => 'Fulfilled', 'status' => $order->fulfilled_at ? 'completed' : 'pending'];

        return $steps;
    }
}
