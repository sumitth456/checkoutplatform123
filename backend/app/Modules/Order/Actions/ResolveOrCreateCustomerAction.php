<?php

namespace App\Modules\Order\Actions;

use App\Models\Customer;
use App\Modules\Order\Contracts\CustomerRepositoryInterface;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use Illuminate\Validation\ValidationException;

class ResolveOrCreateCustomerAction
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {}

    public function execute(CreateFunnelOrderDTO $dto): Customer
    {
        if ($dto->customerId !== null) {
            $customer = $this->customers->findById($dto->customerId);

            if ($customer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Customer not found.'],
                ]);
            }

            return $customer;
        }

        if (blank($dto->customer->email)) {
            throw ValidationException::withMessages([
                'customer.email' => ['Customer email is required.'],
            ]);
        }

        return $this->customers->findOrCreate(
            customer: $dto->customer,
            billingAddress: $dto->billingAddress,
            shippingAddress: $dto->shippingAddress,
        );
    }
}
