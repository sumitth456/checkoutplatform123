<?php

namespace App\Filament\Admin\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;

class CustomerAddressSchema
{
    /**
     * @return array<int, TextInput>
     */
    public static function fields(string $prefix, string $labelPrefix = ''): array
    {
        $label = fn (string $field): string => trim("{$labelPrefix} {$field}");

        return [
            TextInput::make("{$prefix}.first_name")
                ->label($label('First name'))
                ->maxLength(255),
            TextInput::make("{$prefix}.last_name")
                ->label($label('Last name'))
                ->maxLength(255),
            TextInput::make("{$prefix}.company")
                ->label($label('Company'))
                ->maxLength(255),
            TextInput::make("{$prefix}.line1")
                ->label($label('Address line 1'))
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make("{$prefix}.line2")
                ->label($label('Address line 2'))
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make("{$prefix}.city")
                ->label($label('City'))
                ->maxLength(255),
            TextInput::make("{$prefix}.state")
                ->label($label('State / province'))
                ->maxLength(255),
            TextInput::make("{$prefix}.postal_code")
                ->label($label('Postal code'))
                ->maxLength(32),
            TextInput::make("{$prefix}.country")
                ->label($label('Country'))
                ->maxLength(2)
                ->placeholder('US'),
            TextInput::make("{$prefix}.phone")
                ->label($label('Phone'))
                ->tel()
                ->maxLength(50),
        ];
    }
}
