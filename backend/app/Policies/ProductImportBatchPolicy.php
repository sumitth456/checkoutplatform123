<?php

namespace App\Policies;

use App\Models\ProductImportBatch;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class ProductImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'products.import.view');
    }

    public function view(User $user, ProductImportBatch $batch): bool
    {
        unset($batch);

        return AdminAuthorization::can($user, 'products.import.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'products.import.manage');
    }

    public function update(User $user, ProductImportBatch $batch): bool
    {
        unset($batch);

        return AdminAuthorization::can($user, 'products.import.manage');
    }

    public function manage(User $user, ProductImportBatch $batch): bool
    {
        unset($batch);

        return AdminAuthorization::can($user, 'products.import.manage');
    }

    public function downloadErrors(User $user, ProductImportBatch $batch): bool
    {
        unset($batch);

        return AdminAuthorization::can($user, 'products.import.view');
    }
}
