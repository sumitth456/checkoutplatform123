<?php



namespace App\Filament\Admin\Resources\AdminActivityLogs\Pages;



use App\Filament\Admin\Exports\AdminActivityLogExporter;

use App\Filament\Admin\Resources\AdminActivityLogs\AdminActivityLogResource;

use App\Modules\Admin\Services\AdminLogService;

use App\Support\Admin\AdminAuthorization;

use Filament\Actions\ExportAction;

use Filament\Resources\Pages\ListRecords;

use Filament\Schemas\Components\Tabs\Tab;

use Illuminate\Database\Eloquent\Builder;



class ListAdminActivityLogs extends ListRecords

{

    protected static string $resource = AdminActivityLogResource::class;



    protected function getHeaderActions(): array

    {

        return [

            ExportAction::make()

                ->exporter(AdminActivityLogExporter::class)

                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'logs.export')),

        ];

    }



    public function getTabs(): array

    {

        $counts = app(AdminLogService::class)->adminActivityTabCounts();

        $modules = app(AdminLogService::class)->adminActivityModules();



        $tabs = [

            'all' => Tab::make('All')

                ->badge((string) ($counts['all'] ?? 0)),

        ];



        foreach ($modules as $module) {

            $tabs[$module] = Tab::make(ucfirst(str_replace('_', ' ', $module)))

                ->badge((string) ($counts[$module] ?? 0))

                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('module', $module));

        }



        return $tabs;

    }

}

