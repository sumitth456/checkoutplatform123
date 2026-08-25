<?php

namespace App\Modules\Campaign\Enums;

enum CampaignOfferPosition: string
{
    case PreCheckout = 'pre_checkout';
    case PostCheckout = 'post_checkout';
    case OneClickUpsell = 'one_click_upsell';
    case ThankYou = 'thank_you';
    case CheckoutSidebar = 'checkout_sidebar';
}
