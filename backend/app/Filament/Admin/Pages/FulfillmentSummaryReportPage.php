<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Fulfillment\FulfillmentStatsWidget;
use App\Filament\Admin\Widgets\Fulfillment\FulfillmentStatusChartWidget;
use App\Filament\Admin\Widgets\Fulfillment\FulfillmentVolumeChartWidget;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class FulfillmentSummaryReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Summary Report';

    protected static ?string $title = 'Fulfillment Summary Report';

    protected static ?string $slug = 'fulfillment/summary-report';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment_reports.view');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Fulfillment Summary Report';
    }

    public function mount(): void
    {
        AdminActivityLogger::log(
            module: 'fulfillment',
            event: 'report_viewed',
            description: 'Fulfillment summary report viewed',
        );
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            FulfillmentStatsWidget::class,
            FulfillmentStatusChartWidget::class,
            FulfillmentVolumeChartWidget::class,
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
