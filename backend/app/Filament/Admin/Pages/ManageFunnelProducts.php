<?php



namespace App\Filament\Admin\Pages;



use App\Filament\Admin\Resources\Funnels\FunnelResource;

use App\Models\Funnel;

use App\Models\Product;

use App\Models\SubscriptionPlan;

use App\Modules\Product\Import\Services\FunnelProductBulkService;

use App\Support\Admin\AdminActivityLogger;

use App\Support\Admin\AdminAuthorization;

use BackedEnum;

use Filament\Actions\Action;

use Filament\Actions\BulkAction;

use Filament\Forms\Components\Select;

use Filament\Forms\Components\TextInput;

use Filament\Forms\Components\Toggle;

use Filament\Notifications\Notification;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;

use Filament\Resources\Pages\Page as ResourcePage;

use Filament\Schemas\Components\EmbeddedTable;

use Filament\Schemas\Components\View as SchemaView;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;

use Filament\Tables;

use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Filters\SelectFilter;

use Filament\Tables\Filters\TernaryFilter;

use Filament\Tables\Table;

use Illuminate\Contracts\Support\Htmlable;

use Illuminate\Database\Eloquent\Builder;



class ManageFunnelProducts extends ResourcePage implements Tables\Contracts\HasTable

{

    use InteractsWithRecord;

    use Tables\Concerns\InteractsWithTable;



    protected static string $resource = FunnelResource::class;



    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;



    protected static bool $shouldRegisterNavigation = false;



    public function mount(int|string $record): void

    {

        $this->record = $this->resolveRecord($record);

        $this->record->loadCount('products');



        abort_unless(

            AdminAuthorization::can(auth()->user(), 'funnels.products.manage'),

            403,

        );



        AdminActivityLogger::log(

            module: 'funnels',

            event: 'products_manager_viewed',

            description: "Campaign product manager opened for {$this->record->name}",

            subject: $this->record,

        );

    }



    public static function canAccess(array $parameters = []): bool

    {

        return AdminAuthorization::can(auth()->user(), 'funnels.products.manage');

    }



    public function getTitle(): string|Htmlable

    {

        return "Campaign products — {$this->record->name}";

    }



    protected function getHeaderActions(): array

    {

        return [

            Action::make('back')

                ->label('Back to funnel')

                ->url(FunnelResource::getUrl('view', ['record' => $this->record])),

        ];

    }



    public function content(Schema $schema): Schema

    {

        return $schema->components([

            SchemaView::make('filament.admin.pages.funnel-products-overview')

                ->viewData(fn (): array => [

                    'funnel' => $this->record,

                    'assignedCount' => $this->record->products_count,

                ]),

            EmbeddedTable::make(),

        ]);

    }



    public function table(Table $table): Table

    {

        $funnel = $this->record;

        $service = app(FunnelProductBulkService::class);



        return $table

            ->heading('Catalog products')

            ->description('Search, filter, and bulk manage products assigned to this campaign.')

            ->query(fn (): Builder => Product::query()->with(['category', 'brand']))

            ->columns([

                TextColumn::make('sku')->searchable(),

                TextColumn::make('name')->searchable()->sortable(),

                TextColumn::make('base_price')->money(fn (Product $record) => $record->currency),

                TextColumn::make('status')->badge(),

                TextColumn::make('campaign_assignment')

                    ->label('In campaign')

                    ->badge()

                    ->state(function (Product $record) use ($funnel): string {

                        return $funnel->products()->where('products.id', $record->id)->exists()

                            ? 'Yes'

                            : 'No';

                    })

                    ->color(fn (string $state): string => $state === 'Yes' ? 'success' : 'gray'),

            ])

            ->filters([

                SelectFilter::make('status')

                    ->options([

                        'active' => 'Active',

                        'draft' => 'Draft',

                        'archived' => 'Archived',

                    ]),

                TernaryFilter::make('in_campaign')

                    ->label('In campaign')

                    ->queries(

                        true: fn (Builder $query) => $query->whereHas(

                            'funnels',

                            fn (Builder $builder) => $builder->where('funnels.id', $funnel->id),

                        ),

                        false: fn (Builder $query) => $query->whereDoesntHave(

                            'funnels',

                            fn (Builder $builder) => $builder->where('funnels.id', $funnel->id),

                        ),

                    ),

            ])

            ->searchable(['name', 'sku'])

            ->toolbarActions([

                BulkAction::make('bulk_add')

                    ->label('Bulk add to campaign')

                    ->icon('heroicon-o-plus')

                    ->requiresConfirmation()

                    ->action(fn ($records) => $this->runBulk(fn () => $service->bulkAdd(

                        $this->record,

                        $records->pluck('id')->all(),

                    ), 'Added to campaign')),

                BulkAction::make('bulk_remove')

                    ->label('Bulk remove')

                    ->color('danger')

                    ->requiresConfirmation()

                    ->action(fn ($records) => $this->runBulk(fn () => $service->bulkRemove(

                        $this->record,

                        $records->pluck('id')->all(),

                    ), 'Removed from campaign')),

                BulkAction::make('bulk_replace')

                    ->label('Bulk replace campaign catalog')

                    ->color('warning')

                    ->requiresConfirmation()

                    ->modalDescription('This replaces all products on this campaign with the selected rows only.')

                    ->action(fn ($records) => $this->runBulk(fn () => $service->bulkReplace(

                        $this->record,

                        $records->pluck('id')->all(),

                    ), 'Campaign product list replaced')),

                BulkAction::make('bulk_price')

                    ->label('Bulk price update')

                    ->form([

                        TextInput::make('campaign_price')->numeric()->required()->label('Campaign price'),

                    ])

                    ->action(function ($records, array $data) use ($service): void {

                        $updates = $records->map(fn (Product $product): array => [

                            'product_id' => $product->id,

                            'campaign_price' => (float) $data['campaign_price'],

                        ])->all();



                        $service->bulkUpdatePrice($this->record, $updates);

                        $this->runBulk(null, 'Campaign prices updated');

                    }),

                BulkAction::make('bulk_subscription')

                    ->label('Bulk subscription update')

                    ->form([

                        Toggle::make('is_subscription')->label('Subscription product')->default(false),

                        Select::make('subscription_plan')

                            ->label('Subscription plan')

                            ->options(fn (): array => SubscriptionPlan::query()->orderBy('name')->pluck('name', 'name')->all())

                            ->searchable(),

                    ])

                    ->action(function ($records, array $data) use ($service): void {

                        $updates = $records->map(fn (Product $product): array => [

                            'product_id' => $product->id,

                            'is_subscription' => (bool) ($data['is_subscription'] ?? false),

                            'subscription_plan' => $data['subscription_plan'] ?? null,

                        ])->all();



                        $service->bulkUpdateSubscription($this->record, $updates);

                        $this->runBulk(null, 'Subscription settings updated');

                    }),

                BulkAction::make('bulk_inventory')

                    ->label('Bulk inventory update')

                    ->form([

                        TextInput::make('inventory')->numeric()->required()->integer(),

                    ])

                    ->action(function ($records, array $data) use ($service): void {

                        $updates = $records->map(fn (Product $product): array => [

                            'product_id' => $product->id,

                            'inventory' => (int) $data['inventory'],

                        ])->all();



                        $service->bulkUpdateInventory($this->record, $updates);

                        $this->runBulk(null, 'Inventory updated');

                    }),

            ]);

    }



    private function runBulk(?callable $callback, string $message): void

    {

        if ($callback !== null) {

            $callback();

        }



        $this->record->loadCount('products');



        AdminActivityLogger::log(

            module: 'funnels',

            event: 'products_bulk_updated',

            description: $message,

            subject: $this->record,

        );



        Notification::make()->title($message)->success()->send();

    }

}


