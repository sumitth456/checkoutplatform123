<?php

namespace App\Modules\Campaign\Enums;

enum CampaignOfferTrigger: string
{
    case Always = 'always';
    case CartContainsProduct = 'cart_contains_product';
    case CartMinSubtotal = 'cart_min_subtotal';
    case DeclinedUpsell = 'declined_upsell';
    case PostPurchase = 'post_purchase';
}
