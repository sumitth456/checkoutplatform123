<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans\Schemas;

use App\Modules\Subscription\Enums\BillingCycleType;
use App\Modules\Subscription\Enums\BillingStepType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plan')->schema([
                TextInput::make('name')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                TextInput::make('currency')->default('USD')->maxLength(3)->required(),
                Toggle::make('is_active')->default(true),
                Select::make('retry_policy_id')
                    ->relationship('retryPolicy', 'name')
                    ->searchable()
                    ->required(),
                Select::make('dunning_policy_id')
                    ->relationship('dunningPolicy', 'name')
                    ->searchable(),
                Select::make('payment_gateway_id')
                    ->label('Rebill gateway')
                    ->relationship(
                        'paymentGateway',
                        'name',
                        fn ($query) => $query->where('is_active', true)->where('supports_subscriptions', true)->orderBy('sort_order'),
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Default gateway for rebills on subscriptions using this plan.'),
                Select::make('linked_campaign_id')
                    ->label('Assign to campaign')
                    ->options(fn (): array => \App\Models\Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->nullable()
                    ->dehydrated(false)
                    ->helperText('Links this plan to a campaign. All products in that campaign inherit these subscription settings.'),
            ]),
            Section::make('Billing steps')->schema([
                Repeater::make('steps')
                    ->relationship()
                    ->schema([
                        TextInput::make('step_order')->numeric()->required(),
                        Select::make('billing_type')
                            ->options(collect(BillingStepType::cases())->mapWithKeys(
                                fn (BillingStepType $type) => [$type->value => $type->name]
                            )->all())
                            ->required(),
                        Select::make('cycle_type')
                            ->options(collect(BillingCycleType::cases())->mapWithKeys(
                                fn (BillingCycleType $type) => [$type->value => $type->label()]
                            )->all())
                            ->required(),
                        TextInput::make('days_from_start')->numeric(),
                        TextInput::make('interval_days')->numeric(),
                        TextInput::make('amount')->numeric()->required(),
                        TextInput::make('currency')->maxLength(3),
                    ])
                    ->orderColumn('step_order')
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
