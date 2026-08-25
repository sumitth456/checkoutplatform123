<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Widgets\CallCenter\AgentStatusWidget;
use App\Filament\Admin\Widgets\CallCenter\CallCenterStatsWidget;
use App\Filament\Admin\Widgets\CallCenter\RecentCallLogsWidget;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class CallCenterDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Call Center';

    protected static ?string $title = 'Call Center Dashboard';

    protected static ?string $slug = 'call-center';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'call_center.view');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Call Center Dashboard';
    }

    public function mount(): void
    {
        AdminActivityLogger::log(
            module: 'call_center',
            event: 'viewed',
            description: 'Call center dashboard viewed',
        );
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            CallCenterStatsWidget::class,
            RecentCallLogsWidget::class,
            AgentStatusWidget::class,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getFooterWidgets())),
        ]);
    }
}
