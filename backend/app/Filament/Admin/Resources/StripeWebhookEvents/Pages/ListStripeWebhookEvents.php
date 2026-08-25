<?php



namespace App\Filament\Admin\Resources\StripeWebhookEvents\Pages;



use App\Filament\Admin\Exports\StripeWebhookEventExporter;

use App\Filament\Admin\Resources\StripeWebhookEvents\StripeWebhookEventResource;

use App\Modules\Admin\Services\AdminLogService;

use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;

use App\Support\Admin\AdminAuthorization;

use Filament\Actions\ExportAction;

use Filament\Resources\Pages\ListRecords;

use Filament\Schemas\Components\Tabs\Tab;

use Illuminate\Database\Eloquent\Builder;



class ListStripeWebhookEvents extends ListRecords

{

    protected static string $resource = StripeWebhookEventResource::class;



    protected function getHeaderActions(): array

    {

        return [

            ExportAction::make()

                ->exporter(StripeWebhookEventExporter::class)

                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'logs.export')),

        ];

    }



    public function getTabs(): array

    {

        $counts = app(AdminLogService::class)->webhookEventTabCounts();



        return [

            'all' => Tab::make('All')

                ->badge((string) $counts['all']),

            'pending' => Tab::make('Pending')

                ->badge((string) $counts['pending'])

                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [

                    StripeWebhookProcessingStatus::Pending,

                    StripeWebhookProcessingStatus::Processing,

                ])),

            'processed' => Tab::make('Processed')

                ->badge((string) $counts['processed'])

                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StripeWebhookProcessingStatus::Processed)),

            'failed' => Tab::make('Failed')

                ->badge((string) $counts['failed'])

                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [

                    StripeWebhookProcessingStatus::Failed,

                    StripeWebhookProcessingStatus::Duplicate,

                ])),

        ];

    }

}

