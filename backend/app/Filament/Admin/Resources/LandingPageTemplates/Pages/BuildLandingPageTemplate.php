<?php

namespace App\Filament\Admin\Resources\LandingPageTemplates\Pages;

use App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Builder\LandingPageBuilderDocument;
use App\Modules\LandingPage\Builder\LandingPageComponentRegistry;
use App\Modules\LandingPage\Builder\LandingPageDocumentCompiler;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use App\Modules\LandingPage\Support\LandingPageBuilderPreviewDocument;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

class BuildLandingPageTemplate extends Page
{
    use InteractsWithRecord;

    protected static string $resource = LandingPageTemplateResource::class;

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
            AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage'),
            403,
        );

        $this->hydrateFromRecord();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Template Builder — '.$this->record->name;
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
                    'templates' => [],
                    'builderContext' => 'template',
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

        $template = app(LandingPageTemplateService::class)->autoSave($this->record, [
            'builder_json' => $document,
        ]);

        $this->builderDocument = $document;
        $this->contentHtml = (string) ($template->content_html ?? '');
        $this->contentCss = (string) ($template->content_css ?? '');
        $this->contentJs = (string) ($template->content_js ?? '');
        $this->customHeaderScripts = (string) ($template->custom_header_scripts ?? '');
        $this->customFooterScripts = (string) ($template->custom_footer_scripts ?? '');
        $this->record = $template;
        $this->lastSavedAt = $template->updated_at?->toIso8601String();
        $this->saveState = 'auto';

        $this->dispatch('lp-preview-refresh');
    }

    public function saveVersionNow(): void
    {
        if ($this->builderMode === 'visual') {
            $payload = ['builder_json' => $this->builderDocument];
            $template = app(LandingPageTemplateService::class)->autoSave($this->record, $payload);

            $this->record = $template;
            $this->hydrateFromRecord();
            $this->saveState = 'manual';

            Notification::make()
                ->title('Version saved')
                ->body('Template content saved.')
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
            LandingPageBuilderPreviewDocument::templateCacheKey((string) auth()->id(), $this->record),
            $html,
            now()->addMinutes(30),
        );

        return route('landing-page-templates.builder-preview', ['template' => $this->record])
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
            Action::make('toggle_active')
                ->label(fn (): string => $this->record->is_active ? 'Deactivate' : 'Activate')
                ->icon(fn (): string => $this->record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                ->color(fn (): string => $this->record->is_active ? 'warning' : 'success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record = app(LandingPageTemplateService::class)
                        ->toggleActive($this->record, ! $this->record->is_active);

                    Notification::make()
                        ->title($this->record->is_active ? 'Template activated' : 'Template deactivated')
                        ->success()
                        ->send();
                }),
            Action::make('settings')
                ->label('Template settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(LandingPageTemplateResource::getUrl('edit', ['record' => $this->record])),
            Action::make('preview')
                ->label('Full preview')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('landing-page-templates.preview', $this->record))
                ->openUrlInNewTab(),
            Action::make('back')
                ->label('Back to templates')
                ->url(LandingPageTemplateResource::getUrl('index')),
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

        $template = app(LandingPageTemplateService::class)->autoSave($this->record, [
            'content_html' => $contentHtml,
            'content_css' => $contentCss,
            'content_js' => $contentJs,
            'custom_header_scripts' => $customHeaderScripts,
            'custom_footer_scripts' => $customFooterScripts,
        ]);

        $this->record = $template;
        $this->lastSavedAt = $template->updated_at?->toIso8601String();
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

        $this->record = app(LandingPageTemplateService::class)->autoSave($this->record, $payload);
        $this->hydrateFromRecord();
        $this->saveState = 'manual';

        Notification::make()
            ->title('Template saved')
            ->success()
            ->send();
    }

    private function hydrateFromRecord(): void
    {
        /** @var LandingPageTemplate $record */
        $record = $this->record;

        $this->builderDocument = LandingPageBuilderDocument::normalize($record->builder_json);
        $this->contentHtml = (string) ($record->content_html ?? '');
        $this->contentCss = (string) ($record->content_css ?? '');
        $this->contentJs = (string) ($record->content_js ?? '');
        $this->customHeaderScripts = (string) ($record->custom_header_scripts ?? '');
        $this->customFooterScripts = (string) ($record->custom_footer_scripts ?? '');
        $this->lastSavedAt = $record->updated_at?->toIso8601String();
    }
}
