<?php

namespace App\Console\Commands;

use App\Modules\ShipStation\Services\ShipStationWebhookReceiptRecoveryService;
use Illuminate\Console\Command;

class RecoverShipStationWebhookReceiptsCommand extends Command
{
    protected $signature = 'shipstation:recover-webhook-receipts {--minutes=2 : Age threshold for stuck receipts}';

    protected $description = 'Re-dispatch global tracking jobs for webhook receipts stuck in received/pending outbox.';

    public function handle(ShipStationWebhookReceiptRecoveryService $recovery): int
    {
        if (! (bool) config('shipstation.tracking_sync.dispatch_recovery_enabled', false)) {
            $this->warn('Dispatch recovery disabled (SHIPSTATION_TRACKING_DISPATCH_RECOVERY=false).');

            return self::SUCCESS;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        $dispatched = $recovery->recoverStuckReceipts($minutes);

        $this->info('Redispatched '.$dispatched.' receipt(s).');

        return self::SUCCESS;
    }
}