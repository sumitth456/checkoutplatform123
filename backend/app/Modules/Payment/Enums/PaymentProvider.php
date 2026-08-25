<?php

namespace App\Modules\Payment\Enums;

enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case AuthorizeNet = 'authorize_net';
    case Nmi = 'nmi';
    case SandboxAch = 'sandbox_ach';
    case StripeAch = 'stripe_ach';
    case AuthorizeNetEcheck = 'authorize_net_echeck';
    case NmiAch = 'nmi_ach';
    case UsaEpayAch = 'usaepay_ach';

    public function isAch(): bool
    {
        return in_array($this, [
            self::SandboxAch,
            self::StripeAch,
            self::AuthorizeNetEcheck,
            self::NmiAch,
            self::UsaEpayAch,
        ], true);
    }
}
