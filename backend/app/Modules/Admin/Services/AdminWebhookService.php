<?php

namespace App\Modules\Admin\Services;

use App\Models\PaymentGatewayConfig;
use App\Models\StripeWebhookEvent;
use App\Modules\Payment\Enums\GatewayWebhookStatus;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;
use App\Support\Admin\AdminActivityLogger;
use Illuminate\Support\Collection;

class AdminWebhookService
{
    public function __construct(
        private readonly PaymentGatewayAdminService $gatewayAdmin,
    ) {}

    /**
     * @return list<array{provider: string, label: string, method: string, url: string}>
     */
    public function inboundEndpoints(): array
    {
        $base = url('/api/v1/webhooks');

        return [
            [
                'provider' => PaymentProvider::Stripe->value,
                'label' => 'Stripe',
                'method' => 'POST',
                'url' => $base.'/stripe',
            ],
            [
                'provider' => PaymentProvider::PayPal->value,
                'label' => 'PayPal',
                'method' => 'POST',
                'url' => $base.'/'.PaymentProvider::PayPal->value,
            ],
            [
                'provider' => PaymentProvider::AuthorizeNet->value,
                'label' => 'Authorize.Net',
                'method' => 'POST',
                'url' => $base.'/'.PaymentProvider::AuthorizeNet->value,
            ],
            [
                'provider' => PaymentProvider::Nmi->value,
                'label' => 'NMI',
                'method' => 'POST',
                'url' => $base.'/'.PaymentProvider::Nmi->value,
            ],
        ];
    }

    public function endpointForGateway(PaymentGatewayConfig $gateway): ?string
    {
        $provider = PaymentProvider::tryFrom((string) $gateway->provider);

        if ($provider === null) {
            return null;
        }

        return collect($this->inboundEndpoints())
            ->firstWhere('provider', $provider->value)['url'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $since = now()->subDay();
        $gatewayQuery = PaymentGatewayConfig::query();

        $lastEvent = StripeWebhookEvent::query()->orderByDesc('received_at')->first();

        return [
            'endpoints' => $this->inboundEndpoints(),
            'gateways_total' => (clone $gatewayQuery)->count(),
            'gateways_active' => (clone $gatewayQuery)->where('is_active', true)->count(),
            'webhook_verified' => (clone $gatewayQuery)->where('webhook_status', GatewayWebhookStatus::Verified)->count(),
            'webhook_failed' => (clone $gatewayQuery)->where('webhook_status', GatewayWebhookStatus::Failed)->count(),
            'webhook_unknown' => (clone $gatewayQuery)->where('webhook_status', GatewayWebhookStatus::Unknown)->count(),
            'stripe_events_24h' => StripeWebhookEvent::query()->where('received_at', '>=', $since)->count(),
            'stripe_processed_24h' => StripeWebhookEvent::query()
                ->where('received_at', '>=', $since)
                ->where('status', StripeWebhookProcessingStatus::Processed)
                ->count(),
            'stripe_failed_24h' => StripeWebhookEvent::query()
                ->where('received_at', '>=', $since)
                ->whereIn('status', [
                    StripeWebhookProcessingStatus::Failed,
                    StripeWebhookProcessingStatus::Duplicate,
                ])
                ->count(),
            'last_stripe_event_at' => $lastEvent?->received_at,
            'last_stripe_event_type' => $lastEvent?->event_type,
        ];
    }

    /**
     * @return array{verified: int, failed: int}
     */
    public function tabCounts(): array
    {
        $query = PaymentGatewayConfig::query();

        return [
            'all' => (clone $query)->count(),
            'verified' => (clone $query)->where('webhook_status', GatewayWebhookStatus::Verified)->count(),
            'failed' => (clone $query)->where('webhook_status', GatewayWebhookStatus::Failed)->count(),
            'unknown' => (clone $query)->where('webhook_status', GatewayWebhookStatus::Unknown)->count(),
            'pending' => (clone $query)->where('webhook_status', GatewayWebhookStatus::Pending)->count(),
        ];
    }

    /**
     * @return Collection<int, PaymentGatewayConfig>
     */
    public function gatewaysForTable(): Collection
    {
        return PaymentGatewayConfig::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function verifyGateway(PaymentGatewayConfig $gateway): PaymentGatewayConfig
    {
        $updated = $this->gatewayAdmin->verifyWebhook($gateway);

        AdminActivityLogger::log(
            module: 'webhooks',
            event: 'gateway_verified',
            description: "Webhook configuration checked for {$updated->name}",
            subject: $updated,
            properties: [
                'status' => $updated->webhook_status->value,
            ],
        );

        return $updated;
    }

    /**
     * @return array{verified: int, failed: int}
     */
    public function verifyAllActiveGateways(): array
    {
        $verified = 0;
        $failed = 0;

        PaymentGatewayConfig::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->each(function (PaymentGatewayConfig $gateway) use (&$verified, &$failed): void {
                $result = $this->verifyGateway($gateway);

                if ($result->webhook_status === GatewayWebhookStatus::Verified) {
                    $verified++;

                    return;
                }

                $failed++;
            });

        AdminActivityLogger::log(
            module: 'webhooks',
            event: 'bulk_verified',
            description: 'Bulk webhook verification run for active gateways',
            properties: [
                'verified' => $verified,
                'failed' => $failed,
            ],
        );

        return [
            'verified' => $verified,
            'failed' => $failed,
        ];
    }

    public function resetGatewayStatus(PaymentGatewayConfig $gateway): PaymentGatewayConfig
    {
        $gateway->webhook_status = GatewayWebhookStatus::Unknown;
        $gateway->webhook_verified_at = null;
        $gateway->webhook_last_error = null;
        $gateway->save();

        AdminActivityLogger::log(
            module: 'webhooks',
            event: 'status_reset',
            description: "Webhook status reset for {$gateway->name}",
            subject: $gateway,
        );

        return $gateway->fresh();
    }
}
