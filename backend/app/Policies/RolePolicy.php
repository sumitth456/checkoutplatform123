<?php



namespace App\Policies;



use App\Models\Role;

use App\Models\User;

use App\Support\Admin\AdminAuthorization;

use App\Support\Admin\AdminRoleGuard;



class RolePolicy

{

    public function viewAny(User $user): bool

    {

        return AdminAuthorization::can($user, 'roles.view');

    }



    public function view(User $user, Role $role): bool

    {

        return AdminAuthorization::can($user, 'roles.view');

    }



    public function create(User $user): bool

    {

        return AdminAuthorization::can($user, 'roles.create');

    }



    public function update(User $user, Role $role): bool

    {

        if (! AdminAuthorization::can($user, 'roles.update')) {

            return false;

        }



        return ! AdminRoleGuard::isProtectedRole($role->name);

    }



    public function delete(User $user, Role $role): bool

    {

        if (! AdminAuthorization::can($user, 'roles.delete')) {

            return false;

        }



        return ! AdminRoleGuard::isProtectedRole($role->name);

    }

}

