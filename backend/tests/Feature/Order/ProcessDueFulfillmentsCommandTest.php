<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Services\DelayFulfillmentEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessDueFulfillmentsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_queues_due_fulfillment_jobs(): void
    {
        $order = Order::factory()->create([
            'metadata' => ['fulfillment_delay_days' => 0],
        ]);

        $fulfillment = app(DelayFulfillmentEngineService::class)->createDelayedFulfillmentRecord($order);
        $fulfillment->update(['scheduled_ship_date' => now()->startOfDay()]);

        Artisan::call('fulfillments:process-due');

        $this->assertSame(FulfillmentRecordStatus::Scheduled, $fulfillment->fresh()->status);
        $this->assertSame(0, Artisan::call('fulfillments:process-due'));
    }
}
