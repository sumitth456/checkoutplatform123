<?php

namespace App\Modules\Email\Console;

use App\Modules\Email\Services\MailjetProviderBootstrapService;
use Illuminate\Console\Command;

class SyncMailjetProviderCommand extends Command
{
    protected $signature = 'email:sync-mailjet-provider';

    protected $description = 'Create or update the Mailjet email provider record from environment variables';

    public function handle(MailjetProviderBootstrapService $bootstrap): int
    {
        if (! $bootstrap->isConfigured()) {
            $this->error('MAILJET_API_KEY and MAILJET_SECRET_KEY must be set in the environment.');

            return self::FAILURE;
        }

        $provider = $bootstrap->syncFromEnvironment();

        $this->info('Mailjet provider synced.');
        $this->line('  Name: '.($provider?->name ?? 'Mailjet'));
        $this->line('  From: '.($provider?->from_name ?? '').' <'.($provider?->from_email ?? '').'>');
        $this->line('  Default: '.($provider?->is_default ? 'yes' : 'no'));
        $this->line('  Status: '.($provider?->status?->value ?? 'unknown'));

        return self::SUCCESS;
    }
}
