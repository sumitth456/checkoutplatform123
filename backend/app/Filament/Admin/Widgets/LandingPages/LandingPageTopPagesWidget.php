<?php

namespace App\Filament\Admin\Widgets\LandingPages;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use App\Filament\Admin\Widgets\LandingPages\Concerns\InteractsWithLandingPageAnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class LandingPageTopPagesWidget extends TableWidget
{
    use InteractsWithLandingPageAnalyticsPeriod;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.view');
    }

    public function table(Table $table): Table
    {
        $snapshot = $this->landingPageAnalyticsSnapshot();
        $rows = collect($snapshot['top_pages'] ?? [])
            ->values()
            ->map(fn (array $row, int $index): array => [
                'id' => (string) ($index + 1),
                'uuid' => $row['uuid'],
                'name' => $row['name'],
                'views' => $row['views'],
                'conversions' => $row['conversions'],
                'revenue' => $row['revenue'],
                'conversion_rate' => $row['conversion_rate'],
            ]);

        return $table
            ->heading('Top landing pages')
            ->description('By revenue in selected range')
            ->records(fn (): Collection => $rows)
            ->columns([
                TextColumn::make('name')
                    ->label('Page')
                    ->url(fn (array $record): ?string => filled($record['uuid'] ?? null)
                        ? LandingPageResource::getUrl('builder', ['record' => $record['uuid']])
                        : PublishedLandingPages::getUrl()),
                TextColumn::make('views')->numeric(),
                TextColumn::make('conversions')->numeric(),
                TextColumn::make('revenue')
                    ->formatStateUsing(fn ($state): string => Number::currency((float) $state, 'USD')),
                TextColumn::make('conversion_rate')
                    ->label('CR')
                    ->formatStateUsing(fn ($state): string => $state.'%'),
            ])
            ->paginated(false);
    }
}
