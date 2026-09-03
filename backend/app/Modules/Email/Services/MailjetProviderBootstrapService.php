<?php

namespace App\Modules\Email\Services;

use App\Models\EmailProvider;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;

final class MailjetProviderBootstrapService
{
    public function isConfigured(): bool
    {
        return filled(config('email.mailjet.api_key'))
            && filled(config('email.mailjet.secret_key'));
    }

    public function syncFromEnvironment(): ?EmailProvider
    {
        if (! $this->isConfigured()) {
            return EmailProvider::query()
                ->where('provider_type', EmailProviderType::Mailjet)
                ->first();
        }

        $fromEmail = (string) (config('email.mailjet.from_address') ?: config('mail.from.address'));
        $fromName = (string) (config('email.mailjet.from_name') ?: config('mail.from.name'));
        $replyTo = (string) (config('email.mailjet.reply_to') ?: $fromEmail);

        if (config('email.mailjet.default_provider', true)) {
            EmailProvider::query()->update(['is_default' => false]);
        }

        return EmailProvider::query()->updateOrCreate(
            ['provider_type' => EmailProviderType::Mailjet],
            [
                'name' => 'Mailjet',
                'api_key' => (string) config('email.mailjet.api_key'),
                'secret_key' => (string) config('email.mailjet.secret_key'),
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'reply_to_email' => $replyTo,
                'reply_to_name' => $fromName,
                'status' => EmailProviderStatus::Enabled,
                'priority' => 1,
                'is_default' => (bool) config('email.mailjet.default_provider', true),
            ],
        );
    }
}
