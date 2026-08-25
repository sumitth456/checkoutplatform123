<?php

namespace App\Modules\Affiliate\Enums;

enum AffiliatePaymentMethod: string
{
    case Paypal = 'paypal';
    case Wire = 'wire';
    case Bank = 'bank';
    case Crypto = 'crypto';
}
