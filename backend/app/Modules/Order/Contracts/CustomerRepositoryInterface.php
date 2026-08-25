<?php

namespace App\Modules\Order\Contracts;

use App\Models\Customer;
use App\Modules\Order\DTOs\AddressDTO;
use App\Modules\Order\DTOs\CustomerDataDTO;

interface CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer;

    public function findOrCreate(
        CustomerDataDTO $customer,
        ?AddressDTO $billingAddress = null,
        ?AddressDTO $shippingAddress = null,
    ): Customer;

    public function recordOrderPlaced(Customer $customer, float $grandTotal): void;
}
