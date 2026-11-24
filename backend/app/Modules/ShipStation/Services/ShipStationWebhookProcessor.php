<?php

namespace App\Modules\ShipStation\Services;

class ShipStationWebhookProcessor
{
    public function __construct(
        private readonly ShipStationWebhookIngress $ingress,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $this->ingress->handle($payload);
    }
}