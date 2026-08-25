<?php

namespace App\Modules\Ach\Enums;

enum AchProviderType: string
{
    case Sandbox = 'sandbox_ach';
    case StripeAch = 'stripe_ach';
    case AuthorizeNetEcheck = 'authorize_net_echeck';
    case NmiAch = 'nmi_ach';
    case UsaEpayAch = 'usaepay_ach';

    public function label(): string
    {
        return match ($this) {
            self::Sandbox => 'Sandbox ACH',
            self::StripeAch => 'Stripe ACH',
            self::AuthorizeNetEcheck => 'Authorize.net eCheck',
            self::NmiAch => 'NMI ACH',
            self::UsaEpayAch => 'USAePay ACH',
        };
    }
}
