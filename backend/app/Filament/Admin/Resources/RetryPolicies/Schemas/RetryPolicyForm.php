<?php

namespace App\Filament\Admin\Resources\RetryPolicies\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RetryPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Retry policy')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('max_attempts')
                        ->label('Max retry attempts')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(3)
                        ->required()
                        ->helperText('After this many failed retries, the subscription is cancelled.'),
                    Repeater::make('retry_offsets')
                        ->label('Retry offsets (days)')
                        ->helperText('Days to wait before each retry. Example: 1, 3, 5 for three retries. If there are fewer values than attempts, the last value is reused.')
                        ->schema([
                            TextInput::make('days')
                                ->label('Days until retry')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->defaultItems(3)
                        ->minItems(1)
                        ->reorderable()
                        ->columnSpanFull()
                        ->itemLabel(fn (array $state): ?string => isset($state['days'])
                            ? 'Retry after '.$state['days'].' day(s)'
                            : null),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeForForm(array $data): array
    {
        if (! isset($data['retry_offsets']) || ! is_array($data['retry_offsets'])) {
            return $data;
        }

        $offsets = $data['retry_offsets'];

        if ($offsets === []) {
            return $data;
        }

        if (array_is_list($offsets) && ! is_array($offsets[0] ?? null)) {
            $data['retry_offsets'] = array_map(
                fn ($days): array => ['days' => $days],
                $offsets,
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeForSave(array $data): array
    {
        if (! isset($data['retry_offsets']) || ! is_array($data['retry_offsets'])) {
            return $data;
        }

        $data['retry_offsets'] = array_values(array_filter(array_map(
            function ($row): int {
                if (is_array($row)) {
                    return (int) ($row['days'] ?? 0);
                }

                return (int) $row;
            },
            $data['retry_offsets'],
        ), fn (int $days): bool => $days > 0));

        return $data;
    }
}
