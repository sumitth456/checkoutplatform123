<?php

namespace App\Modules\Order\Actions;

use App\Models\Order;

class GenerateOrderNumberAction
{
    public function execute(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
