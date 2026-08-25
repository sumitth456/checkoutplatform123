<?php

namespace App\Filament\Admin\Support\Infolists;

use Filament\Schemas\Schema;

final class StandardEntityInfolist
{
    /**
     * @param  array<int, mixed>  $detailComponents
     */
    public static function layout(string $timelineHeading, array $detailComponents): array
    {
        $components = [
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::activityTimeline($timelineHeading),
        ];

        if ($detailComponents !== []) {
            $components[] = AdminInfolistComponents::detailsSection('Details', $detailComponents);
        }

        return $components;
    }
}
