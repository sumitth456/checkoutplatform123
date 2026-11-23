<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Services\RebillRecoveryShowcaseFixture;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RebillRecoveryShowcaseFixtureTest extends TestCase
{
    #[Test]
    public function showcase_fixture_is_marked_simulated_and_uses_mock_transaction_ids(): void
    {
        $payload = app(RebillRecoveryShowcaseFixture::class)->build();

        $this->assertTrue($payload['is_simulated']);
        $this->assertStringContainsString('SIMULATED', (string) $payload['banner']);

        $cycles = $payload['subscription_histories'][0]['rebill_cycles'];
        $this->assertGreaterThanOrEqual(3, count($cycles));

        foreach ($cycles as $cycle) {
            foreach ($cycle['retry_attempts'] as $step) {
                $this->assertStringStartsWith('SIM-MOCK-', (string) $step['transaction_reference']);
            }
        }
    }
}