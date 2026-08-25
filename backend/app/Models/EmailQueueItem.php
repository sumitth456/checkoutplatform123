<?php

namespace App\Models;

use App\Modules\Email\Enums\EmailQueueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class EmailQueueItem extends Model
{
    protected $fillable = [
        'uuid',
        'email_template_id',
        'email_provider_id',
        'email_automation_id',
        'status',
        'recipient_email',
        'recipient_name',
        'subject',
        'html_body',
        'plain_text_body',
        'metadata',
        'idempotency_key',
        'attempts',
        'max_attempts',
        'scheduled_at',
        'queued_at',
        'processing_at',
        'sent_at',
        'failed_at',
        'last_error',
        'context_type',
        'context_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailQueueStatus::class,
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'queued_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailQueueItem $item): void {
            if (blank($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(EmailProvider::class, 'email_provider_id');
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(EmailAutomation::class, 'email_automation_id');
    }

    public function log(): HasOne
    {
        return $this->hasOne(EmailLog::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
