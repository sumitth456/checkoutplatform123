<?php

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class AdminAuthorization
{
    public static function can(Authenticatable | null $user, string $permission): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return true;
        }

        if ($user->checkPermissionTo($permission)) {
            return true;
        }

        return $user->hasRole(['Admin', 'Super Admin']);
    }
}
