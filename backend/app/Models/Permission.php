<?php

namespace App\Models;

use App\Policies\PermissionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Spatie\Permission\Models\Permission as SpatiePermission;

#[UsePolicy(PermissionPolicy::class)]
class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'group',
        'description',
    ];
}
