<?php

namespace Tests\Feature\Subscription;

use App\Models\Subscription;
use App\Modules\Subscription\Jobs\ChargeSubscriptionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessDueSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_queues_one_job_per_due_subscription(): void
    {
        Queue::fake();

        Subscription::factory()->dueNow()->count(2)->create();
        Subscription::factory()->create([
            'next_billing_at' => now()->addDays(5),
        ]);

        Artisan::call('subscriptions:process-due');

        Queue::assertPushed(ChargeSubscriptionJob::class, 2);
    }
}
