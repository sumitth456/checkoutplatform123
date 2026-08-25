<?php

namespace App\Filament\Admin\Resources\LandingPageTemplates\Pages;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource;
use App\Models\Campaign;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListLandingPageTemplates extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $resource = LandingPageTemplateResource::class;

    protected static ?string $title = 'Templates';

    protected static ?string $navigationLabel = 'Templates';

    protected string $view = 'filament.admin.resources.landing-page-templates.grid';

    public string $search = '';

    public string $categoryFilter = '';

    public string $statusFilter = 'all';

    public string $sortBy = 'updated_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $useTemplate = request()->query('use_template');

        if (filled($useTemplate)) {
            $this->mountAction('useTemplate', ['templateUuid' => (string) $useTemplate]);
        }
    }

    /**
     * @return Collection<int, LandingPageTemplate>
     */
    public function getTemplatesProperty(): Collection
    {
        return $this->templatesQuery()->get();
    }

    public function getTemplateCountProperty(): int
    {
        return $this->templatesQuery()->count();
    }

    /**
     * @return list<string>
     */
    public function getCategoriesProperty(): array
    {
        return LandingPageTemplate::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New template')
                ->icon('heroicon-o-plus')
                ->url(LandingPageTemplateResource::getUrl('create'))
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage')),
        ];
    }

    public function duplicateTemplate(string $uuid): void
    {
        abort_unless(AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage'), 403);

        $template = LandingPageTemplate::query()->where('uuid', $uuid)->firstOrFail();

        app(LandingPageTemplateService::class)->duplicate($template, auth()->id());

        Notification::make()->title('Template duplicated')->success()->send();
    }

    public function toggleTemplateActive(string $uuid): void
    {
        abort_unless(AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage'), 403);

        $template = LandingPageTemplate::query()->where('uuid', $uuid)->firstOrFail();

        app(LandingPageTemplateService::class)->toggleActive($template, ! $template->is_active);

        Notification::make()
            ->title($template->fresh()->is_active ? 'Template activated' : 'Template deactivated')
            ->success()
            ->send();
    }

    public function deleteTemplate(string $uuid): void
    {
        abort_unless(AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage'), 403);

        LandingPageTemplate::query()->where('uuid', $uuid)->firstOrFail()->delete();

        Notification::make()->title('Template deleted')->success()->send();
    }

    public function useTemplateAction(): Action
    {
        return Action::make('useTemplate')
            ->label('Use template')
            ->icon('heroicon-o-document-plus')
            ->modalHeading('Create landing page from template')
            ->modalDescription('A new independent landing page will be created. The template itself will not be modified.')
            ->form([
                TextInput::make('name')
                    ->label('Landing page name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->helperText('Leave blank to auto-generate from the name.')
                    ->maxLength(120)
                    ->alphaDash(),
                Select::make('campaign_id')
                    ->label('Campaign')
                    ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('category')
                    ->label('Category')
                    ->maxLength(80),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->fillForm(function (array $arguments): array {
                $template = $this->resolveTemplateFromArguments($arguments);

                return [
                    'name' => $template?->name ?? '',
                    'category' => $template?->category,
                    'description' => $template?->description,
                ];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $template = $this->resolveTemplateFromArguments($arguments);

                abort_if($template === null, 404);

                $page = app(LandingPageTemplateService::class)->createPageFromTemplate(
                    $template,
                    [
                        'name' => $data['name'],
                        'slug' => filled($data['slug'] ?? null) ? $data['slug'] : null,
                        'campaign_id' => $data['campaign_id'],
                        'meta_description' => $data['description'] ?? null,
                    ],
                    auth()->id(),
                );

                Notification::make()
                    ->title('Landing page created')
                    ->body('“'.$page->name.'” is ready in Published Pages.')
                    ->success()
                    ->send();

                $action->redirect(PublishedLandingPages::getUrl());
            });
    }

    private function templatesQuery(): Builder
    {
        $query = LandingPageTemplate::query();

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        if ($this->categoryFilter !== '') {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderBy(match ($this->sortBy) {
            'name' => 'name',
            'created_at' => 'created_at',
            default => 'updated_at',
        }, $direction);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function resolveTemplateFromArguments(array $arguments): ?LandingPageTemplate
    {
        $uuid = (string) ($arguments['templateUuid'] ?? '');

        if ($uuid === '') {
            return null;
        }

        return LandingPageTemplate::query()->where('uuid', $uuid)->first();
    }
}
