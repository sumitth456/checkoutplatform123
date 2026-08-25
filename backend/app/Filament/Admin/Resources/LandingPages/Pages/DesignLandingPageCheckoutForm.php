<?php

namespace App\Filament\Admin\Resources\LandingPages\Pages;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use App\Models\LandingPage;
use App\Modules\LandingPage\Forms\CheckoutFormFieldType;
use App\Modules\LandingPage\Forms\LandingPageCheckoutFormRenderer;
use App\Modules\LandingPage\Services\LandingPageFormService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class DesignLandingPageCheckoutForm extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = LandingPageResource::class;

    protected static ?string $navigationLabel = null;

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $formData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            AdminAuthorization::can(auth()->user(), 'landing_pages.update'),
            403,
        );

        $checkoutForm = app(LandingPageFormService::class)->ensureDefaultCheckoutForm($this->record);

        $this->form->fill([
            'submit_label' => $checkoutForm->settings['submit_label'] ?? 'Complete checkout',
            'auto_inject' => (bool) ($checkoutForm->settings['auto_inject'] ?? true),
            'fields' => $checkoutForm->field_schema ?? [],
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Checkout form — '.$this->record->name;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            SchemaView::make('filament.admin.resources.landing-pages.checkout-form-designer')
                ->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('published_pages')
                ->label('Published Pages')
                ->icon('heroicon-o-arrow-left')
                ->url(PublishedLandingPages::getUrl()),
            Action::make('builder')
                ->label('Page builder')
                ->url(LandingPageResource::getUrl('builder', ['record' => $this->record])),
            Action::make('reset')
                ->label('Reset defaults')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(LandingPageFormService::class)->resetToDefaults($this->record);
                    $this->mount($this->record->getKey());
                    Notification::make()->title('Form reset')->success()->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Settings')->schema([
                TextInput::make('submit_label')->required()->maxLength(80),
                Toggle::make('auto_inject')
                    ->label('Auto-inject on public page when HTML has no checkout form'),
            ]),
            Section::make('Fields')->schema([
                Repeater::make('fields')
                    ->label('Form fields')
                    ->schema([
                        TextInput::make('key')->required()->maxLength(64),
                        Select::make('type')
                            ->options(collect(CheckoutFormFieldType::cases())->mapWithKeys(
                                fn (CheckoutFormFieldType $type): array => [$type->value => $type->label()]
                            )->all())
                            ->required(),
                        TextInput::make('label')->maxLength(120),
                        TextInput::make('name')->maxLength(120)->helperText('Input name attribute sent to checkout API'),
                        TextInput::make('placeholder')->maxLength(255),
                        Toggle::make('required')->default(false),
                        Toggle::make('enabled')->default(true),
                        TextInput::make('sort_order')->numeric()->default(10),
                        TextInput::make('default')->label('Default value')->maxLength(255),
                    ])
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->addActionLabel('Add field')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(LandingPageFormService::class)->updateCheckoutForm(
            $this->record,
            $data['fields'] ?? [],
            [
                'submit_label' => $data['submit_label'] ?? 'Complete checkout',
                'auto_inject' => (bool) ($data['auto_inject'] ?? true),
            ],
        );

        Notification::make()->title('Checkout form saved')->success()->send();
    }

    public function saveAndInjectHtml(): void
    {
        $this->save();

        app(LandingPageFormService::class)->injectFormIntoPageHtml($this->record->fresh());

        Notification::make()
            ->title('Form HTML injected')
            ->body('Updated #checkout-form in page HTML. Open the builder to customize layout.')
            ->success()
            ->send();
    }

    public function previewHtml(): string
    {
        $form = app(LandingPageFormService::class)->ensureDefaultCheckoutForm($this->record->fresh());

        return app(LandingPageCheckoutFormRenderer::class)->render($form);
    }
}
