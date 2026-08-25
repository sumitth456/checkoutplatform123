<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Widgets\Email\EmailAnalyticsStatsWidget;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class EmailAnalytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 47;

    protected static ?string $navigationLabel = 'Email Analytics';

    protected static ?string $title = 'Email Analytics';

    protected static ?string $slug = 'email-analytics';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'email.analytics.view');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Email Analytics';
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            EmailAnalyticsStatsWidget::class,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(1)
                ->schema($this->getWidgetsSchemaComponents($this->getFooterWidgets())),
        ]);
    }
}
