<?php

namespace App\Modules\Ach\Services;

use App\Models\AchProvider;
use App\Models\Customer;
use App\Models\CustomerPaymentMethod;
use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Ach\Enums\AchAccountType;
use App\Modules\Ach\Enums\AchHolderType;
use App\Modules\Ach\Enums\AchProviderStatus;
use App\Modules\Ach\Factory\AchGatewayFactory;
use App\Modules\Ach\Support\AchValidator;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Exceptions\PaymentGatewayException;

final class AchTokenizationService
{
    public function __construct(
        private readonly AchGatewayFactory $factory,
        private readonly AchPaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function tokenizeFromInput(array $input, ?Customer $customer = null): AchTokenizeResponse
    {
        $errors = AchValidator::validateBankDetails($input);

        if ($errors !== []) {
            throw PaymentGatewayException::transportFailure(
                provider: \App\Modules\Payment\Enums\PaymentProvider::SandboxAch,
                message: implode(' ', array_values($errors)),
                context: ['validation_errors' => $errors, 'action' => 'tokenize'],
            );
        }

        $provider = $this->resolveProvider($input['ach_provider'] ?? null);
        $gateway = $this->factory->forProvider($provider);

        $request = new AchTokenizeRequest(
            accountHolderName: trim((string) $input['account_holder_name']),
            routingNumber: AchValidator::normalizeRoutingNumber((string) $input['routing_number']),
            accountNumber: AchValidator::normalizeAccountNumber((string) $input['account_number']),
            accountType: AchAccountType::from((string) $input['account_type']),
            holderType: AchHolderType::from((string) ($input['account_holder_type'] ?? 'personal')),
            bankName: filled($input['bank_name'] ?? null) ? trim((string) $input['bank_name']) : null,
            customerId: $customer?->id,
            idempotencyKey: $input['idempotency_key'] ?? null,
        );

        try {
            $response = $gateway->tokenize($request);

            $this->logger->log(
                action: 'tokenize',
                status: 'success',
                request: $input,
                response: [
                    'payment_token' => $response->paymentToken,
                    'last4' => $response->last4,
                    'account_type' => $response->accountType->value,
                ],
                provider: $provider,
                customerId: $customer?->id,
                idempotencyKey: $request->idempotencyKey,
            );

            if ($customer !== null) {
                $this->storePaymentMethod($customer, $provider, $gateway, $response, $input);
            }

            return $response;
        } catch (PaymentGatewayException $exception) {
            $this->logger->log(
                action: 'tokenize',
                status: 'failed',
                request: $input,
                response: ['error' => $exception->getMessage()],
                provider: $provider,
                customerId: $customer?->id,
                idempotencyKey: $request->idempotencyKey,
            );

            throw $exception;
        }
    }

    public function testConnection(AchProvider $provider): bool
    {
        $gateway = $this->factory->forProvider($provider);

        $response = $gateway->tokenize(new AchTokenizeRequest(
            accountHolderName: 'Test Holder',
            routingNumber: '110000000',
            accountNumber: '1234567890',
            accountType: AchAccountType::Checking,
            holderType: AchHolderType::Personal,
        ));

        return filled($response->paymentToken);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function storePaymentMethod(
        Customer $customer,
        AchProvider $provider,
        PaymentGatewayInterface $gateway,
        AchTokenizeResponse $response,
        array $input,
    ): CustomerPaymentMethod {
        CustomerPaymentMethod::query()
            ->where('customer_id', $customer->id)
            ->where('payment_method', 'ach')
            ->update(['is_default' => false]);

        return CustomerPaymentMethod::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'payment_token' => $response->paymentToken,
            ],
            [
                'ach_provider_id' => $provider->id,
                'payment_method' => 'ach',
                'provider' => $provider->provider,
                'account_type' => $response->accountType,
                'holder_type' => $response->holderType,
                'last4' => $response->last4,
                'bank_name' => filled($input['bank_name'] ?? null) ? trim((string) $input['bank_name']) : null,
                'is_default' => true,
                'metadata' => [
                    'authorization_id' => $response->authorizationId,
                ],
            ],
        );
    }

    private function resolveProvider(?string $slugOrProvider): AchProvider
    {
        if (filled($slugOrProvider)) {
            $provider = AchProvider::query()
                ->where('status', AchProviderStatus::Enabled)
                ->where(function ($query) use ($slugOrProvider): void {
                    $query->where('slug', $slugOrProvider)
                        ->orWhere('provider', $slugOrProvider);
                })
                ->first();

            if ($provider !== null) {
                return $provider;
            }
        }

        $default = AchProvider::query()
            ->where('status', AchProviderStatus::Enabled)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->first();

        if ($default !== null) {
            return $default;
        }

        throw PaymentGatewayException::transportFailure(
            provider: \App\Modules\Payment\Enums\PaymentProvider::SandboxAch,
            message: 'No enabled ACH provider is configured.',
            context: ['action' => 'tokenize'],
        );
    }
}
