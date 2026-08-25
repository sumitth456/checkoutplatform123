<?php



namespace App\Modules\Order\Enums;



enum FunnelStepType: string

{

    case Landing = 'landing';

    case Checkout = 'checkout';

    case Upsell = 'upsell';

    case Downsell = 'downsell';

    case ThankYou = 'thank_you';

}

