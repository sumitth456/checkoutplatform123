<?php

namespace App\Modules\Email\Enums;

enum EmailProviderStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
