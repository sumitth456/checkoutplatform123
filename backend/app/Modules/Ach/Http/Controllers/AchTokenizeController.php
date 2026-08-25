<?php

namespace App\Modules\Ach\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Ach\Http\Requests\TokenizeAchRequest;
use App\Modules\Ach\Services\AchTokenizationService;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Http\JsonResponse;

final class AchTokenizeController extends Controller
{
    public function __invoke(TokenizeAchRequest $request, AchTokenizationService $service): JsonResponse
    {
        $customer = $this->resolveCustomer($request->validated());

        try {
            $response = $service->tokenizeFromInput($request->validated(), $customer);

            return response()->json([
                'data' => [
                    'payment_token' => $response->paymentToken,
                    'last4' => $response->last4,
                    'account_type' => $response->accountType->value,
                    'holder_type' => $response->holderType->value,
                    'payment_method' => 'ach',
                    'provider' => $response->provider->value,
                    'authorization_id' => $response->authorizationId,
                ],
            ], 201);
        } catch (PaymentGatewayException $exception) {
            $errors = $exception->context['validation_errors'] ?? null;

            if (is_array($errors) && $errors !== []) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => $errors,
                ], 422);
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveCustomer(array $input): ?Customer
    {
        $email = trim((string) ($input['customer_email'] ?? ''));

        if ($email === '') {
            return null;
        }

        return Customer::query()->firstOrCreate(
            ['email' => $email],
            array_filter([
                'first_name' => $input['customer_first_name'] ?? null,
                'last_name' => $input['customer_last_name'] ?? null,
                'status' => 'active',
            ]),
        );
    }
}
