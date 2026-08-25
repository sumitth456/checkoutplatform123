<?php

namespace App\Support\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdminActivityLogger
{
    public static function log(
        string $module,
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
    ): AdminActivityLog {
        /** @var User|null $causer */
        $causer = Auth::user();

        return AdminActivityLog::query()->create([
            'log_name' => 'admin',
            'module' => $module,
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'properties' => $properties,
        ]);
    }
}
