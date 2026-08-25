<?php

namespace App\Modules\Variant\Enums;

enum VariantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
