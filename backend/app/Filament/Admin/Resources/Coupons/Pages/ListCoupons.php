<?php

namespace App\Filament\Admin\Resources\Coupons\Pages;

use App\Filament\Admin\Exports\CouponExporter;
use App\Filament\Admin\Resources\Coupons\CouponResource;
use App\Modules\Admin\Services\CouponAdminService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(CouponExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'coupons.export')),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $counts = app(CouponAdminService::class)->tabCounts();

        return [
            'all' => Tab::make('All')
                ->badge((string) $counts['all']),
            'active' => Tab::make('Active')
                ->badge((string) $counts['active'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                    })
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })),
            'scheduled' => Tab::make('Scheduled')
                ->badge((string) $counts['scheduled'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->whereNotNull('starts_at')
                    ->where('starts_at', '>', now())),
            'expired' => Tab::make('Expired')
                ->badge((string) $counts['expired'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())),
            'inactive' => Tab::make('Inactive')
                ->badge((string) $counts['inactive'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', false)),
        ];
    }
}
