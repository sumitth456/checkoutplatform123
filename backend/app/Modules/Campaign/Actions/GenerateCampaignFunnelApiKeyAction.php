<?php

namespace App\Modules\Campaign\Actions;

use Illuminate\Support\Str;

final class GenerateCampaignFunnelApiKeyAction
{
    /**
     * @return array{plain: string, preview: string}
     */
    public function execute(): array
    {
        $plain = 'sfk_'.Str::lower(Str::random(48));
        $preview = substr($plain, 0, 12).'…'.substr($plain, -4);

        return [
            'plain' => $plain,
            'preview' => $preview,
        ];
    }
}
