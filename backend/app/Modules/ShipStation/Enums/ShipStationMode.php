<?php

namespace App\Modules\ShipStation\Enums;

enum ShipStationMode: string
{
    case Mock = 'mock';
    case Real = 'real';
    /** @deprecated Use {@see Real}. Kept for backward compatibility with SHIPSTATION_MODE=live */
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Mock => 'Mock',
            self::Real, self::Live => 'Real',
        };
    }

    public function isMock(): bool
    {
        return $this === self::Mock;
    }

    public function isReal(): bool
    {
        return $this === self::Real || $this === self::Live;
    }

    public static function fromConfig(?string $value = null): self
    {
        $mode = strtolower((string) ($value ?? config('shipstation.mode', self::Mock->value)));

        return match ($mode) {
            self::Real->value, self::Live->value => self::Real,
            default => self::Mock,
        };
    }
}
