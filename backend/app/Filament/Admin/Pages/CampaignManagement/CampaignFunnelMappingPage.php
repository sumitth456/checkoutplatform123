<?php

namespace App\Filament\Admin\Pages\CampaignManagement;

use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Models\CampaignFunnelMapping;
use App\Models\Funnel;
use App\Modules\Campaign\DTOs\CreateCampaignFunnelMappingDTO;
use App\Modules\Campaign\Enums\CampaignFunnelMappingStatus;
use App\Modules\Campaign\Services\CampaignFunnelMappingService;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class CampaignFunnelMappingPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CAMPAIGNS;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Funnel Mapping';

    protected static ?string $slug = 'campaign-management/funnel-mapping';

    protected static ?string $title = 'Funnel mapping';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'campaigns.funnels.manage');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_mapping')
                ->label('New mapping')
                ->form([
                    Select::make('campaign_id')
                        ->label('Campaign')
                        ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    Select::make('funnel_id')
                        ->label('Funnel')
                        ->options(fn (): array => Funnel::query()
                            ->whereDoesntHave('campaignMapping')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('landing_url')->url(),
                    Select::make('status')
                        ->options(collect(CampaignFunnelMappingStatus::cases())->mapWithKeys(
                            fn (CampaignFunnelMappingStatus $status): array => [$status->value => str($status->name)->headline()]
                        )->all())
                        ->default('active'),
                ])
                ->action(function (array $data): void {
                    $campaign = Campaign::query()->findOrFail((int) $data['campaign_id']);
                    $result = app(CampaignFunnelMappingService::class)->create(
                        $campaign,
                        CreateCampaignFunnelMappingDTO::fromArray($data),
                    );

                    Notification::make()
                        ->title('Mapping created')
                        ->body(new HtmlString('API key: <code class="text-xs">'.e($result['api_key']).'</code>'))
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $service = app(CampaignFunnelMappingService::class);

        return $table
            ->query(CampaignFunnelMapping::query()->with(['campaign', 'funnel']))
            ->columns([
                TextColumn::make('campaign.public_id')->label('Campaign ID')->searchable(),
                TextColumn::make('campaign.name')->label('Campaign')->searchable(),
                TextColumn::make('funnel.name')->label('Funnel')->searchable(),
                TextColumn::make('funnel.uuid')->label('Funnel UUID')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('landing_url')->limit(35)->url(fn ($record) => $record->landing_url, true),
                TextColumn::make('api_key_preview')->label('API key preview'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->relationship('campaign', 'name'),
                SelectFilter::make('status')
                    ->options(collect(CampaignFunnelMappingStatus::cases())->mapWithKeys(
                        fn (CampaignFunnelMappingStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                Action::make('view_campaign')
                    ->label('Campaign')
                    ->url(fn (CampaignFunnelMapping $record): string => CampaignResource::getUrl('view', ['record' => $record->campaign])),
                Action::make('regenerate')
                    ->label('Regenerate key')
                    ->requiresConfirmation()
                    ->action(function (CampaignFunnelMapping $record) use ($service): void {
                        $result = $service->regenerateApiKey($record);
                        Notification::make()
                            ->title('New API key')
                            ->body(new HtmlString('<code>'.e($result['api_key']).'</code>'))
                            ->persistent()
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('sort_order');
    }
}
