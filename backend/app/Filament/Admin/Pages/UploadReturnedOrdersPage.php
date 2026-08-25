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

class UploadReturnedOrdersPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Upload Returns';

    protected static ?string $title = 'Upload Returned Orders';

    protected static ?string $slug = 'fulfillment/upload-returns';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment.manage');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Upload Returned Orders';
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
                Section::make('Upload returns CSV')
                    ->description('CSV columns: order_number (or orderId), status (RETURNED or CANCELLED), rma_number (optional)')
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

    public function upload(FulfillmentTrackingImportService $importService): void
    {
        $data = $this->form->getState();
        /** @var TemporaryUploadedFile|null $file */
        $file = $data['file'] ?? null;

        if ($file === null) {
            Notification::make()->title('Choose a CSV file')->danger()->send();

            return;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($file->get())) ?: [];
        $headers = array_map('strtolower', str_getcsv(array_shift($lines) ?: ''));
        $processed = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = array_combine($headers, str_getcsv($line));
            if ($row === false) {
                continue;
            }
            $orderNumber = $row['order_number'] ?? $row['orderid'] ?? null;
            $status = $row['status'] ?? 'RETURNED';
            if ($orderNumber && $importService->processReturn($orderNumber, $status, $row['rma_number'] ?? $row['rmanumber'] ?? null)) {
                $processed++;
            }
        }

        AdminActivityLogger::log(
            module: 'fulfillment',
            event: 'returns_import_uploaded',
            description: "Returned orders CSV processed: {$processed} records",
        );

        Notification::make()
            ->title('Returns import complete')
            ->body("{$processed} fulfillment records updated.")
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
            ->livewireSubmitHandler('upload')
            ->footer([
                Actions::make([
                    Action::make('upload')
                        ->label('Upload & process')
                        ->submit('upload'),
                ]),
            ]);
    }
}
