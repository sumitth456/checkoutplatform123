<?php



namespace App\Policies;



use App\Models\User;

use App\Models\WarehouseSetting;

use App\Support\Admin\AdminAuthorization;



class WarehouseSettingPolicy

{

    public function viewAny(User $user): bool

    {

        return AdminAuthorization::can($user, 'warehouse.view');

    }



    public function view(User $user, WarehouseSetting $warehouseSetting): bool

    {

        return AdminAuthorization::can($user, 'warehouse.view');

    }



    public function update(User $user, WarehouseSetting $warehouseSetting): bool

    {

        return AdminAuthorization::can($user, 'warehouse.manage');

    }

}

