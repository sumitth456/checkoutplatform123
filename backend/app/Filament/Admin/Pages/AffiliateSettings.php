<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliateSetting;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class AffiliateSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Affiliate Settings';

    protected static ?string $title = 'Affiliate Settings';

    protected static ?string $slug = 'affiliate-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'affiliate_settings.manage');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Affiliate Settings';
    }

    public function mount(): void
    {
        $stored = AffiliateSetting::allKeyed();
        $defaults = AffiliateSetting::defaults();

        $this->form->fill(array_merge($defaults, $stored));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make('Program defaults')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('default_cookie_duration_days')
                                ->label('Default cookie duration (days)')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                            TextInput::make('default_commission_value')
                                ->label('Default commission value')
                                ->numeric()
                                ->required()
                                ->step(0.0001),
                            TextInput::make('min_payout_amount')
                                ->label('Minimum payout amount')
                                ->numeric()
                                ->required()
                                ->prefix('$')
                                ->step(0.01),
                            TextInput::make('tracking_url_prefix')
                                ->label('Tracking URL prefix')
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Attribution rules')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('auto_approve_affiliates')
                            ->label('Auto-approve new affiliates'),
                        Toggle::make('require_manual_payout_approval')
                            ->label('Require manual payout approval'),
                        Toggle::make('allow_coupon_attribution')
                            ->label('Allow coupon attribution'),
                        Toggle::make('allow_self_referrals')
                            ->label('Allow self-referrals'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            AffiliateSetting::set((string) $key, $value);
        }

        AdminActivityLogger::log(
            module: 'affiliate_settings',
            event: 'updated',
            description: 'Affiliate settings updated',
        );

        Notification::make()
            ->title('Affiliate settings saved')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save settings')
                        ->submit('save'),
                ]),
            ]);
    }
}
