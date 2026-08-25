<?php

namespace App\Filament\Admin\Resources\Campaigns\Pages;

use App\Filament\Admin\Exports\CampaignExporter;
use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use App\Modules\Campaign\Enums\CampaignStatus;
use App\Modules\Campaign\Services\CampaignService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(CampaignExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'campaigns.export')),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $counts = app(CampaignService::class)->statusCounts();

        return [
            'all' => Tab::make('All')->badge((string) $counts['all']),
            'active' => Tab::make('Active')
                ->badge((string) $counts['active'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CampaignStatus::Active)),
            'draft' => Tab::make('Draft')
                ->badge((string) $counts['draft'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CampaignStatus::Draft)),
            'paused' => Tab::make('Paused')
                ->badge((string) $counts['paused'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CampaignStatus::Paused)),
            'archived' => Tab::make('Archived')
                ->badge((string) $counts['archived'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CampaignStatus::Archived)),
        ];
    }
}
