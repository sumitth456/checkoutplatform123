<?php



namespace App\Policies;



use App\Models\Funnel;

use App\Models\User;

use App\Support\Admin\AdminAuthorization;



class FunnelPolicy

{

    public function viewAny(User $user): bool

    {

        return AdminAuthorization::can($user, 'funnels.view');

    }



    public function view(User $user, Funnel $funnel): bool

    {

        return AdminAuthorization::can($user, 'funnels.view');

    }



    public function create(User $user): bool

    {

        return AdminAuthorization::can($user, 'funnels.create');

    }



    public function update(User $user, Funnel $funnel): bool

    {

        return AdminAuthorization::can($user, 'funnels.update');

    }



    public function delete(User $user, Funnel $funnel): bool

    {

        return AdminAuthorization::can($user, 'funnels.delete');

    }



    public function restore(User $user, Funnel $funnel): bool

    {

        return AdminAuthorization::can($user, 'funnels.delete');

    }



    public function forceDelete(User $user, Funnel $funnel): bool

    {

        return AdminAuthorization::can($user, 'funnels.delete');

    }



    public function export(User $user): bool

    {

        return AdminAuthorization::can($user, 'funnels.export');

    }

}

