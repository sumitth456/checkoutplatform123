<?php

namespace App\Filament\Admin\Pages;

use App\Models\FulfillmentSetting;
use App\Filament\Admin\Support\AdminNavigationGroup;
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

class FulfillmentSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 14;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Fulfillment Settings';

    protected static ?string $slug = 'fulfillment/settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment.manage');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Fulfillment Settings';
    }

    public function mount(): void
    {
        $this->form->fill(array_merge(
            FulfillmentSetting::defaults(),
            FulfillmentSetting::allKeyed(),
        ));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make('Scheduling')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('default_delay_days')
                                ->label('Default delay (days)')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                            TextInput::make('scheduler_time')
                                ->label('Daily process time')
                                ->required()
                                ->placeholder('06:00'),
                            TextInput::make('scheduler_timezone')
                                ->label('Scheduler timezone')
                                ->required()
                                ->placeholder('UTC'),
                        ]),
                    ]),
                Section::make('Automation')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('auto_submit_due_fulfillments')
                            ->label('Auto-submit due fulfillments'),
                        Toggle::make('notify_on_failed_submission')
                            ->label('Notify on failed warehouse submission'),
                        Toggle::make('tracking_import_auto_match')
                            ->label('Auto-match tracking imports to orders'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            FulfillmentSetting::set((string) $key, $value);
        }

        AdminActivityLogger::log(
            module: 'fulfillment',
            event: 'settings_updated',
            description: 'Fulfillment settings updated',
        );

        Notification::make()
            ->title('Fulfillment settings saved')
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
