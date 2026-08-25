<?php

namespace App\Modules\Affiliate\Enums;

enum PromoMaterialType: string
{
    case Banner = 'banner';
    case Image = 'image';
    case EmailSwipe = 'email_swipe';
    case Video = 'video';
    case Pdf = 'pdf';
    case Coupon = 'coupon';
    case LandingPage = 'landing_page';
}
