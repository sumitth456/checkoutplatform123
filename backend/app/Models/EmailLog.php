<?php

namespace App\Models;

use App\Modules\Email\Enums\EmailLogStatus;
use App\Policies\EmailLogPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[UsePolicy(EmailLogPolicy::class)]
class EmailLog extends Model
{
    protected $fillable = [
        'uuid',
        'email_queue_item_id',
        'email_template_id',
        'email_provider_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'status',
        'html_body',
        'raw_email',
        'provider_response',
        'retry_count',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'spam_at',
        'unsubscribed_at',
        'context_type',
        'context_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailLogStatus::class,
            'provider_response' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'spam_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailLog $log): void {
            if (blank($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function queueItem(): BelongsTo
    {
        return $this->belongsTo(EmailQueueItem::class, 'email_queue_item_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(EmailProvider::class, 'email_provider_id');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
