<?php

namespace App\Filament\Admin\Resources\Transactions\Pages;

use App\Filament\Admin\Resources\Transactions\Concerns\LogsTransactionAdminActivity;
use App\Filament\Admin\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    use LogsTransactionAdminActivity;

    protected static string $resource = TransactionResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->logTransactionActivity(
            'viewed',
            "Transaction {$this->record->uuid} viewed",
        );
    }
}
