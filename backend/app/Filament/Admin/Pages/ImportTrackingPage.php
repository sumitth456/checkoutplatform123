<?php

namespace App\Filament\Admin\Pages;

use App\Modules\Fulfillment\Services\FulfillmentTrackingImportService;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class ImportTrackingPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Import Tracking';

    protected static ?string $title = 'Import Tracking';

    protected static ?string $slug = 'fulfillment/import-tracking';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment.manage');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Import Tracking Numbers';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Upload tracking CSV')
                    ->description('CSV columns: order_number, tracking_number, carrier (optional), shipped_at (optional)')
                    ->schema([
                        FileUpload::make('file')
                            ->label('CSV file')
                            ->acceptedFileTypes(['text/csv', 'text/plain'])
                            ->required()
                            ->maxSize(10240)
                            ->storeFiles(false),
                    ]),
            ]);
    }

    public function import(FulfillmentTrackingImportService $importService): void
    {
        $data = $this->form->getState();
        /** @var TemporaryUploadedFile|null $file */
        $file = $data['file'] ?? null;

        if ($file === null) {
            Notification::make()->title('Choose a CSV file')->danger()->send();

            return;
        }

        $result = $importService->importFromCsv($file->get());

        AdminActivityLogger::log(
            module: 'fulfillment',
            event: 'tracking_import_uploaded',
            description: 'Tracking import: '.$result['updated'].' updated, '.$result['skipped'].' skipped',
        );

        $body = "{$result['updated']} fulfillments updated.";
        if ($result['errors'] !== []) {
            $body .= ' Errors: '.implode('; ', array_slice($result['errors'], 0, 3));
        }

        Notification::make()
            ->title('Tracking import complete')
            ->body($body)
            ->success()
            ->send();

        $this->form->fill();
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
            ->livewireSubmitHandler('import')
            ->footer([
                Actions::make([
                    Action::make('import')
                        ->label('Upload & import')
                        ->submit('import'),
                ]),
            ]);
    }
}
