<?php

namespace App\Modules\Admin\Services;

use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderSearchFilterApplicator
{
    /** @param  Builder<\App\Models\Order>  $query */
    public function apply(Builder $query, Request $request): Builder
    {
        if (! $this->hasSearchParams($request)) {
            return $query;
        }

        if ($orderId = $this->stringParam($request, 'of_order_id')) {
            $query->where(function (Builder $inner) use ($orderId): void {
                $inner->where('order_number', 'like', "%{$orderId}%")
                    ->orWhere('uuid', 'like', "%{$orderId}%");

                if (is_numeric($orderId)) {
                    $inner->orWhere('id', (int) $orderId);
                }
            });
        }

        if ($firstName = $this->stringParam($request, 'of_first_name')) {
            $query->where(function (Builder $inner) use ($firstName): void {
                $inner->whereHas('customer', fn (Builder $customer) => $customer->where('first_name', 'like', "%{$firstName}%"))
                    ->orWhere('billing_address->first_name', 'like', "%{$firstName}%");
            });
        }

        if ($lastName = $this->stringParam($request, 'of_last_name')) {
            $query->where(function (Builder $inner) use ($lastName): void {
                $inner->whereHas('customer', fn (Builder $customer) => $customer->where('last_name', 'like', "%{$lastName}%"))
                    ->orWhere('billing_address->last_name', 'like', "%{$lastName}%");
            });
        }

        if ($phone = $this->stringParam($request, 'of_phone')) {
            $exact = $request->boolean('of_phone_exact');

            $query->where(function (Builder $inner) use ($phone, $exact): void {
                if ($exact) {
                    $inner->where('customer_phone', $phone)
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('phone', $phone));
                } else {
                    $inner->where('customer_phone', 'like', "%{$phone}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('phone', 'like', "%{$phone}%"));
                }
            });
        }

        if ($email = $this->stringParam($request, 'of_email')) {
            $exact = $request->boolean('of_email_exact');

            $query->where(function (Builder $inner) use ($email, $exact): void {
                if ($exact) {
                    $inner->where('customer_email', $email)
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('email', $email));
                } else {
                    $inner->where('customer_email', 'like', "%{$email}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('email', 'like', "%{$email}%"));
                }
            });
        }

        if ($company = $this->stringParam($request, 'of_company')) {
            $query->whereHas('customer', fn (Builder $customer) => $customer->where('company', 'like', "%{$company}%"));
        }

        if ($status = $this->stringParam($request, 'of_status')) {
            $query->where('status', OrderStatus::from($status));
        }

        if ($orderType = $this->stringParam($request, 'of_order_type')) {
            $query->where('order_type', OrderType::from($orderType));
        }

        if ($campaignId = $request->integer('of_campaign')) {
            $query->where('platform_campaign_id', $campaignId);
        }

        if ($customerId = $this->stringParam($request, 'of_customer_id')) {
            if (is_numeric($customerId)) {
                $query->where('customer_id', (int) $customerId);
            } else {
                $query->whereHas('customer', fn (Builder $customer) => $customer
                    ->where('uuid', $customerId)
                    ->orWhere('id', $customerId));
            }
        }

        if ($ipAddress = $this->stringParam($request, 'of_ip')) {
            $query->where('ip_address', 'like', "%{$ipAddress}%");
        }

        if ($paymentStatus = $this->stringParam($request, 'of_payment_status')) {
            $query->where('payment_status', OrderPaymentStatus::from($paymentStatus));
        }

        if ($state = $this->stringParam($request, 'of_state')) {
            $query->where(function (Builder $inner) use ($state): void {
                $inner->where('billing_address->state', $state)
                    ->orWhere('shipping_address->state', $state)
                    ->orWhere('billing_address->region', $state)
                    ->orWhere('shipping_address->region', $state);
            });
        }

        if ($country = $this->stringParam($request, 'of_country')) {
            $countryCode = strtoupper($country);

            $query->where(function (Builder $inner) use ($countryCode): void {
                $inner->where('country_code', $countryCode)
                    ->orWhere('billing_address->country', $countryCode)
                    ->orWhere('shipping_address->country', $countryCode);
            });
        }

        if ($currency = $this->stringParam($request, 'of_currency')) {
            $query->where('currency', strtoupper($currency));
        }

        return $query;
    }

    public function hasSearchParams(Request $request): bool
    {
        return $request->hasAny([
            'of_order_id',
            'of_first_name',
            'of_last_name',
            'of_phone',
            'of_email',
            'of_company',
            'of_status',
            'of_order_type',
            'of_campaign',
            'of_customer_id',
            'of_ip',
            'of_payment_status',
            'of_state',
            'of_country',
            'of_currency',
        ]);
    }

    private function stringParam(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return filled($value) ? $value : null;
    }
}
