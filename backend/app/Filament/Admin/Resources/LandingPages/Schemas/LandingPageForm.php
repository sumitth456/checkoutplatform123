<?php

namespace App\Filament\Admin\Resources\LandingPages\Schemas;

use App\Modules\LandingPage\Enums\LandingPageStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LandingPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('General')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('slug')->required()->maxLength(120)->unique(ignoreRecord: true),
                    Select::make('status')
                        ->options(collect(LandingPageStatus::cases())->mapWithKeys(
                            fn (LandingPageStatus $s): array => [$s->value => $s->label()]
                        )->all())
                        ->required()
                        ->native(false),
                    Select::make('campaign_id')
                        ->label('Campaign')
                        ->relationship('campaign', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('template_id')
                        ->label('Template')
                        ->relationship('template', 'name')
                        ->searchable()
                        ->preload(),
                ]),
            ]),
            Section::make('SEO')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('seo_title')->maxLength(255),
                    FileUpload::make('og_image_path')
                        ->label('OG image')
                        ->image()
                        ->directory('landing-pages/og')
                        ->visibility('public'),
                ]),
                Textarea::make('meta_description')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Page builder')
                ->description('Use the Monaco builder for HTML, CSS, JavaScript, live preview, and auto-save.')
                ->columnSpanFull()
                ->schema([]),
        ]);
    }
}
