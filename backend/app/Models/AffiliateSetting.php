<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateSetting extends Model
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
            'default_cookie_duration_days' => 30,
            'default_commission_type' => 'percentage',
            'default_commission_value' => 10,
            'min_payout_amount' => 50,
            'auto_approve_affiliates' => false,
            'require_manual_payout_approval' => true,
            'tracking_url_prefix' => rtrim((string) config('app.url'), '/').'/track',
            'allow_coupon_attribution' => true,
            'allow_self_referrals' => false,
        ];
    }
}
