<?php

namespace App\Modules\Product\Enums;

enum ProductType: string
{
    case Digital = 'digital';
    case Physical = 'physical';
    case Subscription = 'subscription';
}
