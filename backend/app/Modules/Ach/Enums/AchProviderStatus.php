<?php

namespace App\Modules\Ach\Enums;

enum AchProviderStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
