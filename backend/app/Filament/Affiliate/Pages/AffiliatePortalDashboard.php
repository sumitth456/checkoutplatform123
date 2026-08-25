<?php

namespace App\Filament\Affiliate\Pages;

use App\Filament\Affiliate\Widgets\AffiliatePortalStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class AffiliatePortalDashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            AffiliatePortalStatsWidget::class,
        ];
    }
}
