<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'transactions.view');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        unset($transaction);

        return AdminAuthorization::can($user, 'transactions.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'transactions.view');
    }

    public function update(User $user, Transaction $transaction): bool
    {
        unset($transaction);

        return AdminAuthorization::can($user, 'transactions.view');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        unset($transaction);

        return false;
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'transactions.export');
    }
}
