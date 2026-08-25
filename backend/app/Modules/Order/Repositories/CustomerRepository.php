<?php

namespace App\Modules\Order\Repositories;

use App\Models\Customer;
use App\Modules\Order\Contracts\CustomerRepositoryInterface;
use App\Modules\Order\DTOs\AddressDTO;
use App\Modules\Order\DTOs\CustomerDataDTO;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer
    {
        return Customer::query()->find($id);
    }

    public function findOrCreate(
        CustomerDataDTO $customer,
        ?AddressDTO $billingAddress = null,
        ?AddressDTO $shippingAddress = null,
    ): Customer {
        $existing = Customer::query()->where('email', $customer->email)->first();

        if ($existing !== null) {
            $existing->update(array_filter([
                'first_name' => $customer->firstName ?? $existing->first_name,
                'last_name' => $customer->lastName ?? $existing->last_name,
                'phone' => $customer->phone ?? $existing->phone,
                'company' => $customer->company ?? $existing->company,
                'billing_address' => $billingAddress?->toArray() ?? $existing->billing_address,
                'shipping_address' => $shippingAddress?->toArray() ?? $existing->shipping_address,
                'accepts_marketing' => $customer->acceptsMarketing,
            ], fn (mixed $value): bool => $value !== null));

            return $existing->fresh();
        }

        return Customer::query()->create([
            'email' => $customer->email,
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
            'phone' => $customer->phone,
            'company' => $customer->company,
            'billing_address' => $billingAddress?->toArray(),
            'shipping_address' => $shippingAddress?->toArray(),
            'accepts_marketing' => $customer->acceptsMarketing,
            'status' => 'active',
        ]);
    }

    public function recordOrderPlaced(Customer $customer, float $grandTotal): void
    {
        $customer->update([
            'orders_count' => $customer->orders_count + 1,
            'total_spent' => (float) $customer->total_spent + $grandTotal,
            'first_order_at' => $customer->first_order_at ?? now(),
            'last_order_at' => now(),
        ]);
    }
}
