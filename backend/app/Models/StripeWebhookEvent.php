<?php

namespace App\Models;

use App\Policies\StripeWebhookEventPolicy;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[UsePolicy(StripeWebhookEventPolicy::class)]
class StripeWebhookEvent extends Model
{
    protected $fillable = [
        'uuid',
        'stripe_event_id',
        'event_type',
        'status',
        'payload',
        'normalized_payload',
        'attempt_count',
        'last_error',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StripeWebhookProcessingStatus::class,
            'payload' => 'array',
            'normalized_payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StripeWebhookEvent $event): void {
            if (blank($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }

            $event->received_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
