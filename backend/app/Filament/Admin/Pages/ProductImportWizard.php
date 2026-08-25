<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\Funnel;
use App\Models\ProductImportBatch;
use App\Modules\Product\Import\ProductImportFieldRegistry;
use App\Modules\Product\Import\Services\ProductImportBatchService;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class ProductImportWizard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::PRODUCTS;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Import products';

    protected static ?string $slug = 'products/import';

    protected string $view = 'filament.admin.pages.product-import-wizard';

    #[Url(as: 'batch')]
    public ?string $batchUuid = null;

    #[Url(as: 'step')]
    public int $step = 1;

    public ?array $uploadData = [];

    /** @var array<string, int|null> */
    public array $columnMapping = [];

    public ?int $targetFunnelId = null;

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'products.import.manage');
    }

    public function mount(): void
    {
        if ($this->batchUuid !== null) {
            $batch = $this->resolveBatch();
            $this->columnMapping = $batch->column_mapping ?? [];
            $this->targetFunnelId = $batch->target_funnel_id;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Bulk product import';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('uploadData')
            ->components([
                Section::make('Upload spreadsheet')
                    ->visible(fn (): bool => $this->step === 1)
                    ->schema([
                        FileUpload::make('file')
                            ->label('CSV or XLSX file')
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->required()
                            ->maxSize(51200)
                            ->storeFiles(false),
                        Select::make('target_funnel_id')
                            ->label('Assign to campaign after import (optional)')
                            ->options(fn (): array => Funnel::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable(),
                    ]),
            ]);
    }

    public function uploadFile(): void
    {
        $data = $this->form->getState();
        /** @var TemporaryUploadedFile|null $file */
        $file = $data['file'] ?? null;

        if ($file === null) {
            Notification::make()->title('Choose a file')->danger()->send();

            return;
        }

        $batch = app(ProductImportBatchService::class)->createBatchFromUpload(
            $file,
            (int) auth()->id(),
            isset($data['target_funnel_id']) ? (int) $data['target_funnel_id'] : null,
        );

        AdminActivityLogger::log(
            module: 'products',
            event: 'import.uploaded',
            description: "Import file {$batch->original_filename} uploaded",
            subject: $batch,
        );

        $this->batchUuid = $batch->uuid;
        $this->columnMapping = $batch->column_mapping ?? [];
        $this->targetFunnelId = $batch->target_funnel_id;
        $this->step = 2;

        Notification::make()->title('File uploaded')->success()->send();
    }

    public function goToStep(int $step): void
    {
        if ($this->batchUuid === null && $step > 1) {
            return;
        }

        $this->step = max(1, min(5, $step));
    }

    public function runValidation(): void
    {
        $batch = $this->resolveBatch();
        app(ProductImportBatchService::class)->runValidation($batch);
        $this->step = 3;

        Notification::make()
            ->title('Validation complete')
            ->body('Review errors before importing valid rows.')
            ->success()
            ->send();
    }

    public function saveColumnMapping(): void
    {
        $mapping = [];

        foreach ($this->columnMapping as $field => $index) {
            $mapping[$field] = ($index === '' || $index === null) ? null : (int) $index;
        }

        $batch = app(ProductImportBatchService::class)->saveMapping(
            $this->resolveBatch(),
            $mapping,
        );

        $this->columnMapping = $batch->column_mapping ?? [];

        app(ProductImportBatchService::class)->runValidation($batch->fresh());

        $this->step = 3;

        Notification::make()
            ->title('Column mapping saved')
            ->body('Validation complete. Review errors before importing.')
            ->success()
            ->send();
    }

    public function startImport(): void
    {
        $batch = $this->resolveBatch();

        if ($batch->status->value !== 'validated') {
            app(ProductImportBatchService::class)->runValidation($batch);
            $batch = $batch->fresh();
        }

        if ($this->targetFunnelId !== null) {
            $batch->update(['target_funnel_id' => $this->targetFunnelId]);
        }

        app(ProductImportBatchService::class)->queueImport($batch->fresh());

        AdminActivityLogger::log(
            module: 'products',
            event: 'import.queued',
            description: "Product import batch {$batch->uuid} queued",
            subject: $batch,
        );

        $this->step = 5;

        Notification::make()
            ->title('Import queued')
            ->body('Track progress under Import history.')
            ->success()
            ->send();
    }

    public function downloadErrorReport(): mixed
    {
        $batch = $this->resolveBatch();
        $path = app(ProductImportBatchService::class)->generateErrorReportCsv($batch);

        return Storage::disk('local')->download($path, basename($path));
    }

    /**
     * @return array<string, string>
     */
    public function systemFields(): array
    {
        return ProductImportFieldRegistry::fields();
    }

    public function getBatchProperty(): ?ProductImportBatch
    {
        if ($this->batchUuid === null) {
            return null;
        }

        return ProductImportBatch::query()
            ->with(['errors', 'user', 'targetFunnel'])
            ->where('uuid', $this->batchUuid)
            ->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('history')
                ->label('Import history')
                ->url(fn (): string => ProductImportHistory::getUrl())
                ->icon('heroicon-o-clock'),
        ];
    }

    protected function resolveBatch(): ProductImportBatch
    {
        return ProductImportBatch::query()->where('uuid', $this->batchUuid)->firstOrFail();
    }
}
