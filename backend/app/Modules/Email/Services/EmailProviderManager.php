<?php

namespace App\Modules\Email\Services;

use App\Models\EmailProvider;
use App\Modules\Email\Contracts\EmailProviderDriverInterface;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Data\EmailSendResult;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Modules\Email\Providers\HttpEmailProviderDriver;
use App\Modules\Email\Providers\KlaviyoEmailProviderDriver;
use App\Modules\Email\Providers\SmtpEmailProviderDriver;
use Illuminate\Support\Collection;

final class EmailProviderManager
{
    public function __construct(
        private readonly SmtpEmailProviderDriver $smtpDriver,
        private readonly HttpEmailProviderDriver $httpDriver,
        private readonly KlaviyoEmailProviderDriver $klaviyoDriver,
    ) {}

    public function driverFor(EmailProvider $provider): EmailProviderDriverInterface
    {
        return match ($provider->provider_type) {
            EmailProviderType::Smtp => $this->smtpDriver,
            EmailProviderType::Klaviyo => $this->klaviyoDriver,
            default => $this->httpDriver,
        };
    }

    public function testProvider(EmailProvider $provider): EmailSendResult
    {
        return $this->driverFor($provider)->testConnection($provider);
    }

    /**
     * @return Collection<int, EmailProvider>
     */
    public function availableProviders(): Collection
    {
        return EmailProvider::query()
            ->where('status', EmailProviderStatus::Enabled)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->get()
            ->filter(fn (EmailProvider $provider): bool => $this->withinDailyLimit($provider))
            ->values();
    }

    public function sendWithFailover(EmailSendRequest $request): EmailSendResult
    {
        $providers = $this->availableProviders();
        $errors = [];

        foreach ($providers as $provider) {
            if (! $this->withinRateLimit($provider)) {
                continue;
            }

            $result = $this->driverFor($provider)->send($provider, $request);

            if ($result->success) {
                $this->recordSend($provider);

                return new EmailSendResult(
                    success: true,
                    messageId: $result->messageId,
                    response: array_merge($result->response ?? [], [
                        'provider_id' => $provider->id,
                        'provider_name' => $provider->name,
                    ]),
                );
            }

            $errors[] = "{$provider->name}: ".($result->error ?? 'Unknown error');
        }

        return new EmailSendResult(
            success: false,
            error: $errors !== [] ? implode(' | ', $errors) : 'No enabled email providers available.',
            response: ['attempted_providers' => $providers->pluck('name')->all()],
        );
    }

    private function withinDailyLimit(EmailProvider $provider): bool
    {
        if ($provider->daily_limit === null) {
            return true;
        }

        $today = now()->toDateString();

        if ($provider->sent_today_date?->toDateString() !== $today) {
            return true;
        }

        return (int) $provider->sent_today < (int) $provider->daily_limit;
    }

    private function withinRateLimit(EmailProvider $provider): bool
    {
        return $provider->rate_limit_per_minute === null || $provider->rate_limit_per_minute > 0;
    }

    private function recordSend(EmailProvider $provider): void
    {
        $today = now()->toDateString();

        if ($provider->sent_today_date?->toDateString() !== $today) {
            $provider->forceFill([
                'sent_today' => 1,
                'sent_today_date' => $today,
            ])->save();

            return;
        }

        $provider->increment('sent_today');
    }
}
