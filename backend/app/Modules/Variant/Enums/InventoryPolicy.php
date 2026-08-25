<?php

namespace App\Modules\Variant\Enums;

enum InventoryPolicy: string
{
    case Deny = 'deny';
    case Continue = 'continue';
}
