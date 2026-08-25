<?php

namespace App\Filament\Admin\Resources\LandingPages\Pages;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Builder\LandingPageBuilderDocument;
use App\Modules\LandingPage\Builder\LandingPageComponentRegistry;
use App\Modules\LandingPage\Builder\LandingPageDocumentCompiler;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use App\Modules\LandingPage\Support\LandingPageBuilderPreviewDocument;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class BuildLandingPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = LandingPageResource::class;

    protected static ?string $navigationLabel = null;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** @var array<string, mixed> */
    public array $builderDocument = [];

    public string $builderMode = 'visual';

    public string $viewport = 'desktop';

    public ?string $selectedNodeId = null;

    public string $contentHtml = '';

    public string $contentCss = '';

    public string $contentJs = '';

    public string $customHeaderScripts = '';

    public string $customFooterScripts = '';

    public ?string $saveState = null;

    public ?string $lastSavedAt = null;

    public string $activeTab = 'html';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            AdminAuthorization::can(auth()->user(), 'landing_pages.update'),
            403,
        );

        $this->hydrateFromRecord();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Builder — '.$this->record->name;
    }

    public function getPageClasses(): array
    {
        return array_merge(parent::getPageClasses(), ['fi-lp-builder-page']);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            SchemaView::make('filament.admin.resources.landing-pages.builder')
                ->columnSpanFull()
                ->viewData(fn (): array => [
                    'livewire' => $this,
                    'componentDefinitions' => LandingPageComponentRegistry::definitions(),
                    'defaultStyles' => LandingPageComponentRegistry::defaultStyles(),
                    'templates' => LandingPageTemplate::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all(),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBuilderDocumentJsonProperty(): array
    {
        return $this->builderDocument;
    }

    public function setBuilderMode(string $mode): void
    {
        if (! in_array($mode, ['visual', 'code'], true)) {
            return;
        }

        $this->builderMode = $mode;
    }

    public function setViewport(string $viewport): void
    {
        if (! in_array($viewport, ['desktop', 'tablet', 'mobile'], true)) {
            return;
        }

        $this->viewport = $viewport;
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['html', 'css', 'js', 'header', 'footer'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function saveBuilderDocument(array $document): void
    {
        $document = LandingPageBuilderDocument::normalize($document);

        $page = app(LandingPageService::class)->autoSave($this->record, [
            'builder_json' => $document,
        ], auth()->id());

        $this->builderDocument = $document;
        $this->contentHtml = (string) ($page->content_html ?? '');
        $this->contentCss = (string) ($page->content_css ?? '');
        $this->contentJs = (string) ($page->content_js ?? '');
        $this->customHeaderScripts = (string) ($page->custom_header_scripts ?? '');
        $this->customFooterScripts = (string) ($page->custom_footer_scripts ?? '');
        $this->record = $page;
        $this->lastSavedAt = $page->last_auto_saved_at?->toIso8601String();
        $this->saveState = 'auto';

        $this->dispatch('lp-preview-refresh');
    }

    public function saveVersionNow(): void
    {
        if ($this->builderMode === 'visual') {
            $page = app(LandingPageService::class)->saveBuilderVersion($this->record, [
                'builder_json' => $this->builderDocument,
            ], auth()->id());

            $this->record = $page;
            $this->hydrateFromRecord();
            $this->saveState = 'manual';

            Notification::make()
                ->title('Version saved')
                ->body('A new entry was added to version history.')
                ->success()
                ->send();

            return;
        }

        $this->saveVersion(
            $this->contentHtml,
            $this->contentCss,
            $this->contentJs,
            $this->customHeaderScripts,
            $this->customFooterScripts,
        );
    }

    public function loadTemplate(int $templateId): void
    {
        $template = LandingPageTemplate::query()->findOrFail($templateId);
        $document = LandingPageBuilderDocument::normalize($template->builder_json);

        if ($document['nodes'] === [] && filled($template->content_html)) {
            Notification::make()
                ->title('Template has no visual layout')
                ->body('This template only contains legacy HTML. Use code mode or pick a visual template.')
                ->warning()
                ->send();

            return;
        }

        $this->saveBuilderDocument($document);
        $this->selectedNodeId = null;

        Notification::make()
            ->title('Template loaded')
            ->success()
            ->send();
    }

    public function saveAsTemplate(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        app(LandingPageTemplateService::class)->createFromPage(
            $this->record->fresh(['forms', 'assets']),
            $name,
            ['description' => 'Saved from '.$this->record->name],
            auth()->id(),
        );

        Notification::make()
            ->title('Template saved')
            ->body('You can load this template when creating or editing pages.')
            ->success()
            ->send();
    }

    public function getPreviewHtmlProperty(): string
    {
        return LandingPageBuilderPreviewDocument::fromParts(
            $this->customHeaderScripts,
            $this->contentCss,
            $this->contentHtml,
            $this->contentJs,
            $this->customFooterScripts,
        );
    }

    public function getPreviewIframeSrcProperty(): string
    {
        $html = $this->previewHtml;

        Cache::put(
            LandingPageBuilderPreviewDocument::cacheKey((string) auth()->id(), $this->record),
            $html,
            now()->addMinutes(30),
        );

        return route('landing-pages.builder-preview', ['landingPage' => $this->record])
            .'?v='.substr(md5($html), 0, 12);
    }

    public function updated($property): void
    {
        if ($this->builderMode !== 'code') {
            return;
        }

        if (! in_array($property, [
            'contentHtml',
            'contentCss',
            'contentJs',
            'customHeaderScripts',
            'customFooterScripts',
        ], true)) {
            return;
        }

        $this->autoSave(
            $this->contentHtml,
            $this->contentCss,
            $this->contentJs,
            $this->customHeaderScripts,
            $this->customFooterScripts,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Published Pages')
                ->icon('heroicon-o-arrow-left')
                ->url(PublishedLandingPages::getUrl()),
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn (): bool => $this->record->status !== LandingPageStatus::Published)
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        app(LandingPageService::class)->publish($this->record->fresh(), auth()->id());
                        $this->record = $this->record->fresh();
                        Notification::make()->title('Published')->success()->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === LandingPageStatus::Published)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(LandingPageService::class)->unpublish($this->record, auth()->id());
                    $this->record = $this->record->fresh();
                    Notification::make()->title('Unpublished')->success()->send();
                }),
            Action::make('load_template')
                ->label('Load template')
                ->icon('heroicon-o-document-duplicate')
                ->form([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(fn (): array => LandingPageTemplate::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->searchable(),
                ])
                ->action(fn (array $data): mixed => $this->loadTemplate((int) $data['template_id'])),
            Action::make('save_template')
                ->label('Save as template')
                ->icon('heroicon-o-bookmark')
                ->form([
                    TextInput::make('name')
                        ->label('Template name')
                        ->required()
                        ->maxLength(120),
                ])
                ->action(fn (array $data): mixed => $this->saveAsTemplate((string) $data['name'])),
            Action::make('checkout_form')
                ->label('Checkout form')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(fn (): string => LandingPageResource::getUrl('checkout-form', ['record' => $this->record])),
            Action::make('assets')
                ->label('Upload assets')
                ->icon('heroicon-o-photo')
                ->url(fn (): string => \App\Filament\Admin\Resources\LandingPageAssets\LandingPageAssetResource::getUrl('create')),
            Action::make('settings')
                ->label('Page settings')
                ->url(LandingPageResource::getUrl('edit', ['record' => $this->record])),
            Action::make('preview')
                ->label('Full preview')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('landing-pages.preview', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    public function autoSave(
        string $contentHtml,
        string $contentCss,
        string $contentJs,
        string $customHeaderScripts = '',
        string $customFooterScripts = '',
    ): void {
        $this->contentHtml = $contentHtml;
        $this->contentCss = $contentCss;
        $this->contentJs = $contentJs;
        $this->customHeaderScripts = $customHeaderScripts;
        $this->customFooterScripts = $customFooterScripts;

        $page = app(LandingPageService::class)->autoSave($this->record, [
            'content_html' => $contentHtml,
            'content_css' => $contentCss,
            'content_js' => $contentJs,
            'custom_header_scripts' => $customHeaderScripts,
            'custom_footer_scripts' => $customFooterScripts,
        ], auth()->id());

        $this->record = $page;
        $this->lastSavedAt = $page->last_auto_saved_at?->toIso8601String();
        $this->saveState = 'auto';
    }

    public function saveVersion(
        string $contentHtml,
        string $contentCss,
        string $contentJs,
        string $customHeaderScripts = '',
        string $customFooterScripts = '',
    ): void {
        $payload = [
            'content_html' => $contentHtml,
            'content_css' => $contentCss,
            'content_js' => $contentJs,
            'custom_header_scripts' => $customHeaderScripts,
            'custom_footer_scripts' => $customFooterScripts,
        ];

        if ($this->builderMode === 'visual') {
            $payload['builder_json'] = $this->builderDocument;
        }

        $page = app(LandingPageService::class)->saveBuilderVersion($this->record, $payload, auth()->id());

        $this->record = $page;
        $this->hydrateFromRecord();
        $this->saveState = 'manual';

        Notification::make()
            ->title('Version saved')
            ->body('A new entry was added to version history.')
            ->success()
            ->send();
    }

    private function hydrateFromRecord(): void
    {
        /** @var LandingPage $record */
        $record = $this->record;

        $this->builderDocument = LandingPageBuilderDocument::normalize($record->builder_json);
        $this->contentHtml = (string) ($record->content_html ?? '');
        $this->contentCss = (string) ($record->content_css ?? '');
        $this->contentJs = (string) ($record->content_js ?? '');
        $this->customHeaderScripts = (string) ($record->custom_header_scripts ?? '');
        $this->customFooterScripts = (string) ($record->custom_footer_scripts ?? '');
        $this->lastSavedAt = $record->last_auto_saved_at?->toIso8601String();
    }
}
