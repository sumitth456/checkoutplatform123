<?php

namespace App\Filament\Admin\Resources\LandingPages\Pages;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use App\Filament\Admin\Resources\LandingPages\Schemas\LandingPageForm;
use App\Filament\Admin\Support\Actions\SaveLandingPageAsTemplateAction;
use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Services\LandingPageFormService;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateLandingPage extends CreateRecord
{
    protected static string $resource = LandingPageResource::class;

    public function form(Schema $schema): Schema
    {
        $base = LandingPageForm::configure(Schema::make());

        return $schema
            ->columns(2)
            ->components([
                Section::make('Start with')
                    ->description('Choose a blank page or clone an existing template.')
                    ->columnSpanFull()
                    ->schema([
                        Radio::make('creation_mode')
                            ->label('Page type')
                            ->options([
                                'blank' => 'Blank page',
                                'template' => 'Use existing template',
                            ])
                            ->default('blank')
                            ->live()
                            ->required(),
                        Select::make('source_template_id')
                            ->label('Template')
                            ->options(fn (): array => LandingPageTemplate::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->visible(fn (callable $get): bool => $get('creation_mode') === 'template')
                            ->required(fn (callable $get): bool => $get('creation_mode') === 'template'),
                    ]),
                ...$base->getComponents(withActions: false),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $mode = $data['creation_mode'] ?? 'blank';
        $templateId = $data['source_template_id'] ?? null;

        unset($data['creation_mode'], $data['source_template_id']);

        if ($mode === 'template' && filled($templateId)) {
            $template = LandingPageTemplate::query()->findOrFail((int) $templateId);

            return app(LandingPageTemplateService::class)->createPageFromTemplate(
                $template,
                $data,
                auth()->id(),
            );
        }

        $page = app(LandingPageService::class)->create($data, auth()->id());
        app(LandingPageFormService::class)->ensureDefaultCheckoutForm($page);

        return $page;
    }

    protected function afterCreate(): void
    {
        if (($this->data['creation_mode'] ?? 'blank') === 'template') {
            return;
        }

        app(LandingPageService::class)->update($this->record, [], auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return PublishedLandingPages::getUrl();
    }
}
