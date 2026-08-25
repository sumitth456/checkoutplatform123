<?php

namespace App\Models;

use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Policies\EmailProviderPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UsePolicy(EmailProviderPolicy::class)]
class EmailProvider extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'provider_type',
        'api_key',
        'secret_key',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'username',
        'password',
        'from_name',
        'from_email',
        'reply_to_email',
        'reply_to_name',
        'status',
        'priority',
        'rate_limit_per_minute',
        'daily_limit',
        'sent_today',
        'sent_today_date',
        'is_default',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'provider_type' => EmailProviderType::class,
            'status' => EmailProviderStatus::class,
            'api_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'password' => 'encrypted',
            'is_default' => 'boolean',
            'settings' => 'array',
            'sent_today_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailProvider $provider): void {
            if (blank($provider->uuid)) {
                $provider->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (EmailProvider $provider): void {
            if ($provider->is_default) {
                static::query()
                    ->where('id', '!=', $provider->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(EmailQueueItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function isEnabled(): bool
    {
        return $this->status === EmailProviderStatus::Enabled;
    }
}
