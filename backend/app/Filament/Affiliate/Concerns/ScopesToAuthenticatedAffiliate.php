<?php

namespace App\Filament\Affiliate\Concerns;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Builder;

trait ScopesToAuthenticatedAffiliate
{
    protected static function affiliateId(): int
    {
        /** @var Affiliate $affiliate */
        $affiliate = auth('affiliate')->user();

        return (int) $affiliate->id;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('affiliate_id', static::affiliateId());
    }
}
