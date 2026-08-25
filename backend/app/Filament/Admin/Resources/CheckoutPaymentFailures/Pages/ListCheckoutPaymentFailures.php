<?php



namespace App\Filament\Admin\Resources\CheckoutPaymentFailures\Pages;



use App\Filament\Admin\Exports\CheckoutPaymentFailureExporter;

use App\Filament\Admin\Resources\CheckoutPaymentFailures\CheckoutPaymentFailureResource;

use App\Modules\Admin\Services\AdminLogService;

use App\Support\Admin\AdminAuthorization;

use Filament\Actions\ExportAction;

use Filament\Resources\Pages\ListRecords;

use Filament\Schemas\Components\Tabs\Tab;

use Illuminate\Database\Eloquent\Builder;



class ListCheckoutPaymentFailures extends ListRecords

{

    protected static string $resource = CheckoutPaymentFailureResource::class;



    protected function getHeaderActions(): array

    {

        return [

            ExportAction::make()

                ->exporter(CheckoutPaymentFailureExporter::class)

                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'logs.export')),

        ];

    }



    public function getTabs(): array

    {

        $counts = app(AdminLogService::class)->paymentFailureTabCounts();



        return [

            'all' => Tab::make('All')

                ->badge((string) $counts['all']),

            'stripe' => Tab::make('Stripe')

                ->badge((string) $counts['stripe'])

                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('provider', 'stripe')),

        ];

    }

}

