<?php

namespace App\Support\Admin;

use App\Models\Role;

final class AdminRoleGuard
{
    /**
     * @return list<string>
     */
    public static function protectedRoles(): array
    {
        return ['Super Admin', 'Admin'];
    }

    public static function isProtectedRole(?string $roleName): bool
    {
        return $roleName !== null && in_array($roleName, self::protectedRoles(), true);
    }

    public static function isProtected(Role|string|null $role): bool
    {
        if ($role instanceof Role) {
            return self::isProtectedRole($role->name);
        }

        return self::isProtectedRole($role);
    }

    /**
     * @return list<string>
     */
    public static function assignableRoles(): array
    {
        return [
            'Super Admin',
            'Admin',
            'Manager',
            'Customer Support',
            'Finance',
            'Fulfillment',
            'Marketing',
            'Developer',
        ];
    }
}
