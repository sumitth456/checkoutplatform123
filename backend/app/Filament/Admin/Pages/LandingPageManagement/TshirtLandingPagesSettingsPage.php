<?php

namespace App\Filament\Admin\Pages\LandingPageManagement;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Modules\LandingPage\Services\TshirtLandingPageDomainVerificationService;
use App\Modules\LandingPage\Services\TshirtLandingPageSettingsService;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class TshirtLandingPagesSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?string $navigationParentItem = AdminNavigationParent::LANDING_PAGES;

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'T-Shirt Domain';

    protected static ?string $title = 'T-Shirt Landing Pages';

    protected static ?string $slug = 'landing-pages/tshirt-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.view');
    }

    public function getTitle(): string|Htmlable
    {
        return 'T-Shirt Landing Pages';
    }

    public function mount(TshirtLandingPageSettingsService $settings): void
    {
        $this->form->fill([
            'tshirt_domain' => $settings->configuredDomain(),
            'verification_status' => $settings->verificationStatus(),
            'wildcard_dns' => $settings->wildcardHostLabel(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Global T-shirt domain')
                    ->description('Configure one wildcard DNS record (*.yourdomain.com). Individual landing page slugs become subdomains automatically.')
                    ->schema([
                        TextInput::make('tshirt_domain')
                            ->label('T-shirt landing page domain')
                            ->placeholder('famteesworld.com')
                            ->helperText('Enter the root domain only — no https:// or www.')
                            ->maxLength(255)
                            ->required(),
                        Placeholder::make('wildcard_dns')
                            ->label('Wildcard DNS')
                            ->content(fn (): string => app(TshirtLandingPageSettingsService::class)->wildcardHostLabel()),
                        Placeholder::make('verification_status')
                            ->label('Verification status')
                            ->content(fn (): string => str(app(TshirtLandingPageSettingsService::class)->verificationStatus())->headline()),
                    ]),
            ]);
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
                        ->label('Save domain')
                        ->submit('save'),
                    Action::make('verify')
                        ->label('Verify domain')
                        ->color('gray')
                        ->action('verifyDomain'),
                ]),
            ]);
    }

    public function save(TshirtLandingPageSettingsService $settings): void
    {
        $state = $this->form->getState();
        $settings->saveDomain($state['tshirt_domain'] ?? null);
        $settings->saveVerificationStatus('pending');

        Notification::make()
            ->title('T-shirt domain saved')
            ->success()
            ->send();

        $this->mount($settings);
    }

    public function verifyDomain(TshirtLandingPageDomainVerificationService $verification): void
    {
        $result = $verification->verify();

        Notification::make()
            ->title('Domain verification')
            ->body($result['message'])
            ->success($result['status'] === 'configured')
            ->warning($result['status'] !== 'configured')
            ->send();

        $this->form->fill(array_merge($this->data ?? [], [
            'verification_status' => $result['status'],
        ]));
    }
}
