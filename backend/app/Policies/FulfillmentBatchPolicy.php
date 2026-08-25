<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class FulfillmentBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_batches.view');
    }

    public function view(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_batches.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_batches.create');
    }

    public function update(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_batches.update');
    }

    public function delete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_batches.delete');
    }

    public function restore(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_batches.delete');
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_batches.delete');
    }
}
