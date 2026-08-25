<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LandingPageForm extends Model
{
    protected $fillable = [
        'uuid',
        'landing_page_id',
        'name',
        'form_key',
        'field_schema',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_schema' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LandingPageForm $form): void {
            if (blank($form->uuid)) {
                $form->uuid = (string) Str::uuid();
            }
        });
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
