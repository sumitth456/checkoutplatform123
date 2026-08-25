<?php

namespace App\Modules\Subscription\Services;

use App\Models\Subscription;
use App\Modules\Ach\Services\AchCheckoutPaymentService;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Modules\Payment\Services\PaymentOrchestrator;
use App\Modules\Subscription\Contracts\BillingLedgerInterface;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Events\SubscriptionRebilled;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Throwable;

class RebillService
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
        private readonly AchCheckoutPaymentService $achCheckoutPayment,
        private readonly CycleAmountResolver $cycleAmountResolver,
        private readonly BillingCycleAdvancer $cycleAdvancer,
        private readonly RetryPolicyResolver $retryPolicyResolver,
        private readonly BillingLedgerInterface $billingLedger,
        private readonly DunningService $dunningService,
        private readonly RebillGatewayLoadBalancer $gatewayLoadBalancer,
    ) {}

    public function charge(Subscription $subscription): void
    {
        if (! $subscription->isBillable()) {
            return;
        }

        if (blank($subscription->payment_token)) {
            throw new InvalidArgumentException('Subscription is missing a payment token.');
        }

        if ($this->isAchSubscription($subscription)) {
            $this->chargeAchSubscription($subscription);

            return;
        }

        $resolved = $this->cycleAmountResolver->resolve($subscription);
        $gateways = $this->gatewayLoadBalancer->gatewaysForCharge($subscription);

        if ($gateways === []) {
            throw new InvalidArgumentException('No eligible payment gateway is available for rebill routing.');
        }

        $lastException = null;
        $lastLedgerEntry = null;

        foreach ($gateways as $gatewayConfig) {
            $provider = $this->gatewayLoadBalancer->providerForGateway($gatewayConfig);
            $idempotencyKey = $this->buildIdempotencyKey($subscription, $provider);

            if ((float) $resolved['amount'] <= 0) {
                $this->recordZeroAmountSuccess($subscription, $resolved, $idempotencyKey, $gatewayConfig);

                return;
            }

            $request = new PaymentRequest(
                amount: $resolved['amount'],
                currency: $resolved['currency'],
                paymentToken: $subscription->payment_token,
                customerId: (string) $subscription->customer_id,
                provider: $provider,
                idempotencyKey: $idempotencyKey,
                metadata: [
                    'subscription_id' => $subscription->uuid,
                    'cycle_step' => $subscription->current_cycle_step,
                    'payment_gateway_id' => $gatewayConfig->id,
                    'rebill_attempt' => $subscription->retry_count,
                ],
            );

            try {
                $authorize = $this->orchestrator->processPayment($request);

                $response = $this->orchestrator->capture(
                    transactionId: $authorize->transactionId,
                    amount: $resolved['amount'],
                    currency: $resolved['currency'],
                    provider: $authorize->provider->value,
                    idempotencyKey: $idempotencyKey.'-capture',
                );

                $lastLedgerEntry = $this->billingLedger->recordAttempt(
                    subscription: $subscription,
                    outcome: BillingLedgerOutcome::Success,
                    amount: $resolved['amount'],
                    currency: $resolved['currency'],
                    idempotencyKey: $idempotencyKey,
                    cycleStep: $subscription->current_cycle_step,
                    retryAttempt: $subscription->retry_count,
                    response: $response,
                );

                $this->retryPolicyResolver->resetRetries($subscription);
                $this->dunningService->reset($subscription);
                $subscription->payment_provider = $provider->value;
                $subscription->payment_gateway_id = $gatewayConfig->id;
                $subscription->metadata = array_merge($subscription->metadata ?? [], [
                    'last_successful_rebill_gateway_id' => $gatewayConfig->id,
                ]);
                $this->cycleAdvancer->advanceAfterSuccess($subscription, $resolved['step']);
                $subscription->save();

                Event::dispatch(new SubscriptionRebilled($subscription, $response, $lastLedgerEntry));

                return;
            } catch (Throwable $exception) {
                $lastException = $exception;

                $failureCode = $exception instanceof PaymentGatewayException
                    ? ($exception->context['code'] ?? null)
                    : null;

                $lastLedgerEntry = $this->billingLedger->recordAttempt(
                    subscription: $subscription,
                    outcome: BillingLedgerOutcome::Failure,
                    amount: $resolved['amount'],
                    currency: $resolved['currency'],
                    idempotencyKey: $idempotencyKey,
                    cycleStep: $subscription->current_cycle_step,
                    retryAttempt: $subscription->retry_count + 1,
                    failureCode: $failureCode,
                    failureMessage: $exception->getMessage(),
                );
            }
        }

        if ($lastException !== null) {
            $this->retryPolicyResolver->handleFailure($subscription);

            Event::dispatch(new SubscriptionRebillFailed($subscription, $lastException->getMessage(), $lastLedgerEntry));
        }
    }

    private function recordZeroAmountSuccess(
        Subscription $subscription,
        array $resolved,
        string $idempotencyKey,
        \App\Models\PaymentGatewayConfig $gatewayConfig,
    ): void {
        $ledgerEntry = $this->billingLedger->recordAttempt(
            subscription: $subscription,
            outcome: BillingLedgerOutcome::Success,
            amount: $resolved['amount'],
            currency: $resolved['currency'],
            idempotencyKey: $idempotencyKey,
            cycleStep: $subscription->current_cycle_step,
            retryAttempt: $subscription->retry_count,
        );

        $this->retryPolicyResolver->resetRetries($subscription);
        $this->dunningService->reset($subscription);
        $subscription->payment_gateway_id = $gatewayConfig->id;
        $this->cycleAdvancer->advanceAfterSuccess($subscription, $resolved['step']);
        $subscription->save();

        Event::dispatch(new SubscriptionRebilled($subscription, null, $ledgerEntry));
    }

    public function buildIdempotencyKey(Subscription $subscription, ?PaymentProvider $provider = null): string
    {
        $billDate = $subscription->next_billing_at?->toIso8601String() ?? 'unknown';
        $providerKey = $provider?->value ?? 'route';

        return "subscription:{$subscription->id}:{$billDate}:retry:{$subscription->retry_count}:{$providerKey}";
    }

    private function isAchSubscription(Subscription $subscription): bool
    {
        if ($subscription->payment_method_type === 'ach') {
            return true;
        }

        return str_starts_with((string) $subscription->payment_token, 'tok_ach_');
    }

    private function chargeAchSubscription(Subscription $subscription): void
    {
        $resolved = $this->cycleAmountResolver->resolve($subscription);
        $idempotencyKey = $this->buildIdempotencyKey($subscription);

        if ((float) $resolved['amount'] <= 0) {
            $this->retryPolicyResolver->resetRetries($subscription);
            $this->dunningService->reset($subscription);
            $this->cycleAdvancer->advanceAfterSuccess($subscription, $resolved['step']);
            $subscription->save();

            return;
        }

        $request = new PaymentRequest(
            amount: $resolved['amount'],
            currency: $resolved['currency'],
            paymentToken: (string) $subscription->payment_token,
            customerId: (string) $subscription->customer_id,
            idempotencyKey: $idempotencyKey,
            metadata: [
                'subscription_id' => $subscription->uuid,
                'cycle_step' => $subscription->current_cycle_step,
                'rebill_attempt' => $subscription->retry_count,
                'payment_method' => 'ach',
            ],
        );

        try {
            $result = $this->achCheckoutPayment->processAuthorizeAndCapture($request);
            $response = $result->captureResponse;

            $ledgerEntry = $this->billingLedger->recordAttempt(
                subscription: $subscription,
                outcome: BillingLedgerOutcome::Success,
                amount: $resolved['amount'],
                currency: $resolved['currency'],
                idempotencyKey: $idempotencyKey,
                cycleStep: $subscription->current_cycle_step,
                retryAttempt: $subscription->retry_count,
                response: $response,
            );

            $this->retryPolicyResolver->resetRetries($subscription);
            $this->dunningService->reset($subscription);
            $this->cycleAdvancer->advanceAfterSuccess($subscription, $resolved['step']);
            $subscription->payment_method_type = 'ach';
            $subscription->payment_provider = $response->provider->value;
            $subscription->save();

            Event::dispatch(new SubscriptionRebilled($subscription, $response, $ledgerEntry));
        } catch (Throwable $exception) {
            $this->retryPolicyResolver->handleFailure($subscription);

            $ledgerEntry = $this->billingLedger->recordAttempt(
                subscription: $subscription,
                outcome: BillingLedgerOutcome::Failure,
                amount: $resolved['amount'],
                currency: $resolved['currency'],
                idempotencyKey: $idempotencyKey,
                cycleStep: $subscription->current_cycle_step,
                retryAttempt: $subscription->retry_count + 1,
                failureMessage: $exception->getMessage(),
            );

            Event::dispatch(new SubscriptionRebillFailed($subscription, $exception->getMessage(), $ledgerEntry));
        }
    }
}
