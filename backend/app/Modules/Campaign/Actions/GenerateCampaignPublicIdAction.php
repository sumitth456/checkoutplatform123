<?php

namespace App\Modules\Campaign\Actions;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

final class GenerateCampaignPublicIdAction
{
    public function execute(): string
    {
        return DB::transaction(function (): string {
            $datePrefix = now()->format('Ymd');
            $latest = Campaign::query()
                ->where('public_id', 'like', "CMP-{$datePrefix}-%")
                ->orderByDesc('public_id')
                ->lockForUpdate()
                ->value('public_id');

            $sequence = 1;

            if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('CMP-%s-%04d', $datePrefix, $sequence);
        });
    }
}
