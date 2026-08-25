<?php

namespace App\Modules\Order\DTOs;

use App\Models\Campaign;
use App\Models\Customer;

readonly class PreparedCheckoutDTO
{
    /**
     * @param  list<ResolvedLineItemDTO>  $lineItems
     */
    public function __construct(
        public Customer $customer,
        public array $lineItems,
        public OrderTotalsDTO $totals,
        public OrderAttributionDTO $attribution,
        public ?Campaign $campaign = null,
        public ?CreateFunnelOrderDTO $checkoutDto = null,
    ) {}
}
