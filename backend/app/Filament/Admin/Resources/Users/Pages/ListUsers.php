<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Exports\UserExporter;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'users.create')),
            ExportAction::make()
                ->exporter(UserExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'users.export')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'active')),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', '!=', 'active')),
        ];
    }
}
