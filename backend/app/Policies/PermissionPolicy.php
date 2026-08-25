<?php



namespace App\Policies;



use App\Models\Permission;

use App\Models\User;

use App\Support\Admin\AdminAuthorization;



class PermissionPolicy

{

    public function viewAny(User $user): bool

    {

        return AdminAuthorization::can($user, 'permissions.view');

    }



    public function view(User $user, Permission $permission): bool

    {

        return AdminAuthorization::can($user, 'permissions.view');

    }



    public function create(User $user): bool

    {

        return false;

    }



    public function update(User $user, Permission $permission): bool

    {

        return AdminAuthorization::can($user, 'permissions.manage');

    }



    public function delete(User $user, Permission $permission): bool

    {

        return false;

    }

}

