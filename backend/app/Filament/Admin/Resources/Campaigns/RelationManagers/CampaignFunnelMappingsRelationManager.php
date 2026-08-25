<?php

namespace App\Filament\Admin\Resources\Campaigns\RelationManagers;

use App\Models\CampaignFunnelMapping;
use App\Models\Funnel;
use App\Modules\Campaign\Enums\CampaignFunnelMappingStatus;
use App\Modules\Campaign\Services\CampaignFunnelMappingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CampaignFunnelMappingsRelationManager extends RelationManager
{
    protected static string $relationship = 'funnelMappings';

    protected static ?string $title = 'Funnel mapping';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('funnel_id')
                ->label('Funnel')
                ->options(fn (): array => Funnel::query()
                    ->whereDoesntHave('campaignMapping')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->visibleOn('create'),
            TextInput::make('landing_url')->label('Landing URL')->url()->maxLength(2048),
            Select::make('status')
                ->options(collect(CampaignFunnelMappingStatus::cases())->mapWithKeys(
                    fn (CampaignFunnelMappingStatus $status): array => [$status->value => str($status->name)->headline()]
                )->all())
                ->required(),
            TextInput::make('sort_order')->numeric()->default(0),
            TextInput::make('api_key_preview')->label('API key preview')->disabled()->dehydrated(false),
        ]);
    }

    public function table(Table $table): Table
    {
        $service = app(CampaignFunnelMappingService::class);

        return $table
            ->columns([
                TextColumn::make('funnel.name')->label('Funnel name')->searchable(),
                TextColumn::make('funnel.uuid')->label('Funnel ID')->copyable(),
                TextColumn::make('landing_url')->label('Landing URL')->limit(40)->url(fn ($record) => $record->landing_url, true),
                TextColumn::make('api_key_preview')->label('API key'),
                TextColumn::make('status')->badge(),
                TextColumn::make('sort_order')->label('Sort'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['campaign_id'] = $this->getOwnerRecord()->getKey();

                        return $data;
                    })
                    ->using(function (array $data) use ($service): CampaignFunnelMapping {
                        $result = $service->create(
                            $this->getOwnerRecord(),
                            \App\Modules\Campaign\DTOs\CreateCampaignFunnelMappingDTO::fromArray($data),
                        );

                        Notification::make()
                            ->title('API key generated')
                            ->body(new HtmlString('<code class="text-xs">'.e($result['api_key']).'</code>'))
                            ->persistent()
                            ->success()
                            ->send();

                        return $result['mapping'];
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('regenerate_api_key')
                    ->label('Regenerate API key')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->action(function (CampaignFunnelMapping $record) use ($service): void {
                        $result = $service->regenerateApiKey($record);

                        Notification::make()
                            ->title('New API key')
                            ->body(new HtmlString('<code class="text-xs">'.e($result['api_key']).'</code>'))
                            ->persistent()
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->using(fn (CampaignFunnelMapping $record) => $service->delete($record)),
            ]);
    }
}
