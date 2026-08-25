<?php

namespace App\Filament\Admin\Widgets\Fulfillment;

use App\Models\Fulfillment;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class FulfillmentStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Fulfillment status mix';

    protected ?string $description = 'Current fulfillment records by status';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment.view')
            || AdminAuthorization::can(auth()->user(), 'delay_fulfillment.view');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $statuses = FulfillmentRecordStatus::cases();
        $labels = [];
        $data = [];
        $colors = ['#f59e0b', '#3b82f6', '#22c55e', '#10b981', '#ef4444', '#6b7280'];

        foreach ($statuses as $index => $status) {
            $labels[] = str($status->name)->headline();
            $data[] = Fulfillment::query()->where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                ],
            ],
            'labels' => $labels,
        ];
    }
}
