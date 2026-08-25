<?php

namespace App\Filament\Admin\Support\Infolists;

use App\Modules\Admin\Services\EntitySummaryService;
use App\Modules\Admin\Services\Timeline\EntityTimelineResolver;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Illuminate\Database\Eloquent\Model;

final class AdminInfolistComponents
{
    public static function summaryHeader(?Model $explicitRecord = null): SchemaView
    {
        return SchemaView::make('filament.admin.components.entity-summary-header')
            ->columnSpanFull()
            ->viewData(function ($record = null) use ($explicitRecord): array {
                $resolved = $explicitRecord ?? $record;

                return [
                    'summary' => $resolved instanceof Model
                        ? app(EntitySummaryService::class)->forRecord($resolved)
                        : [],
                ];
            });
    }

    public static function activityTimeline(?string $heading = 'Activity timeline'): SchemaView
    {
        return SchemaView::make('filament.admin.components.activity-timeline')
            ->columnSpanFull()
            ->viewData(fn (Model $record): array => [
                'heading' => $heading,
                'timeline' => app(EntityTimelineResolver::class)->present($record),
            ]);
    }

    /**
     * @param  array<int, mixed>  $schema
     */
    public static function detailsSection(string $title, array $schema, bool $collapsed = true): Section
    {
        return Section::make($title)
            ->columnSpanFull()
            ->collapsed($collapsed)
            ->schema($schema);
    }
}
