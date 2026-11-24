<?php

namespace App\Modules\ShipStation\Support;

readonly class ShipStationSubmissionDecision
{
    public function __construct(
        public bool $eligible,
        public ?string $reasonCode = null,
        public ?string $landingOverrideSlug = null,
    ) {}

    public static function eligible(): self
    {
        return new self(eligible: true);
    }

    public static function ineligible(string $reasonCode, ?string $landingOverrideSlug = null): self
    {
        return new self(
            eligible: false,
            reasonCode: $reasonCode,
            landingOverrideSlug: $landingOverrideSlug,
        );
    }

    public function skipMessage(string $action = 'shipment'): string
    {
        if ($this->reasonCode === 'ineligible_refunded') {
            return 'Refunded orders cannot be sent to ShipStation.';
        }

        if ($this->reasonCode === 'ineligible_invalid_customer_email') {
            return 'Invalid customer email orders cannot be sent to ShipStation.';
        }

        if ($this->reasonCode === 'ineligible_test_customer') {
            return $action === 'label'
                ? 'ShipStation label skipped: test customer.'
                : 'ShipStation shipment skipped: test customer.';
        }

        if ($this->reasonCode === 'ineligible_face_shields_only') {
            return 'Order contains only face shield products; no ShipStation shipment was created.';
        }

        if ($this->reasonCode === 'ineligible_test_order') {
            return 'ShipStation submission skipped: test order.';
        }

        if ($this->reasonCode === 'ineligible_rebill') {
            return 'Rebill orders are not sent to ShipStation.';
        }

        if ($this->reasonCode === 'ineligible_sandbox_payment') {
            return 'ShipStation submission skipped: Sandbox/test orders are not eligible for fulfillment.';
        }

        if ($this->reasonCode === null || $this->reasonCode === '') {
            return 'ShipStation submission was skipped.';
        }

        return 'ShipStation submission skipped: Sandbox/test orders are not eligible for fulfillment.';
    }
}
