<?php

namespace App\Modules\Campaign\Enums;

enum CampaignOrderBumpDisplayRule: string
{
    case Always = 'always';
    case CartMinSubtotal = 'cart_min_subtotal';
    case CartContainsProduct = 'cart_contains_product';
    case FirstOrder = 'first_order';
}
