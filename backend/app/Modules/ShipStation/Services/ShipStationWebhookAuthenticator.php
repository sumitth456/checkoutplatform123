<?php

namespace App\Modules\ShipStation\Services;

use App\Modules\ShipStation\Exceptions\ShipStationWebhookException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShipStationWebhookAuthenticator
{
    /**
     * ShipStation V1 webhooks do not sign request bodies. Authenticity is established by
     * registering an unguessable shared token in the webhook target URL query string.
     *
     * @see https://help.shipstation.com/hc/en-us/articles/360025856252-ShipStation-Webhooks
     */
    public function verify(Request $request): void
    {
        $secret = config('shipstation.webhook_secret');

        if (blank($secret)) {
            Log::warning('ShipStation webhook rejected: secret not configured');

            throw ShipStationWebhookException::notConfigured();
        }

        $token = (string) $request->query('token', '');

        if ($token === '' || ! hash_equals($secret, $token)) {
            Log::warning('ShipStation webhook rejected: invalid authentication');

            throw ShipStationWebhookException::unauthorized();
        }
    }
}
