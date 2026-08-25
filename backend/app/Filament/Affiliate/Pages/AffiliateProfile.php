<?php

namespace App\Filament\Affiliate\Pages;

use App\Models\Affiliate;
use App\Modules\Affiliate\Enums\AffiliatePaymentMethod;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AffiliateProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Profile';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Profile';

    protected static ?string $slug = 'profile';

    protected string $view = 'filament.affiliate.pages.affiliate-profile';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        /** @var Affiliate $affiliate */
        $affiliate = auth('affiliate')->user();

        $this->form->fill([
            'company' => $affiliate->company,
            'name' => $affiliate->name,
            'email' => $affiliate->email,
            'phone' => $affiliate->phone,
            'payment_method' => $affiliate->payment_method?->value,
            'postback_url' => $affiliate->postback_url,
            'tracking_id' => $affiliate->tracking_id,
            'api_key' => $affiliate->api_key,
            'notes' => $affiliate->notes,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('company'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                Select::make('payment_method')
                    ->options(collect(AffiliatePaymentMethod::cases())->mapWithKeys(
                        fn (AffiliatePaymentMethod $method): array => [$method->value => str($method->name)->headline()]
                    )->all()),
                TextInput::make('postback_url')
                    ->label('Postback URL')
                    ->url(),
                TextInput::make('tracking_id')
                    ->label('Tracking ID')
                    ->disabled(),
                TextInput::make('api_key')
                    ->label('API Key')
                    ->disabled()
                    ->copyable(),
                Textarea::make('notes')
                    ->rows(3),
            ]);
    }

    public function save(): void
    {
        /** @var Affiliate $affiliate */
        $affiliate = auth('affiliate')->user();

        $data = $this->form->getState();

        $affiliate->update([
            'company' => $data['company'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'postback_url' => $data['postback_url'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Notification::make()
            ->title('Profile updated')
            ->success()
            ->send();
    }
}
