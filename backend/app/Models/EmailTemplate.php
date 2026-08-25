<?php

namespace App\Models;

use App\Policies\EmailTemplatePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UsePolicy(EmailTemplatePolicy::class)]
class EmailTemplate extends Model
{
    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'category',
        'trigger_event',
        'subject',
        'html_body',
        'plain_text_body',
        'variables',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailTemplate $template): void {
            if (blank($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }

            if (blank($template->slug) && filled($template->name)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(EmailAutomation::class);
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(EmailQueueItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }
}
