<?php

namespace App\Models;

use App\Policies\EmailAutomationPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UsePolicy(EmailAutomationPolicy::class)]
class EmailAutomation extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'trigger_event',
        'email_template_id',
        'delay_minutes',
        'conditions',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailAutomation $automation): void {
            if (blank($automation->uuid)) {
                $automation->uuid = (string) Str::uuid();
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

    public function queueItems(): HasMany
    {
        return $this->hasMany(EmailQueueItem::class);
    }
}
