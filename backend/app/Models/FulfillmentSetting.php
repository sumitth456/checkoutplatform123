<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function set(string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function allKeyed(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'default_delay_days' => (int) config('fulfillment.default_delay_days', 10),
            'scheduler_time' => (string) config('fulfillment.scheduler.time', '06:00'),
            'scheduler_timezone' => (string) config('fulfillment.scheduler.timezone', 'UTC'),
            'auto_submit_due_fulfillments' => true,
            'notify_on_failed_submission' => true,
            'tracking_import_auto_match' => true,
        ];
    }
}
