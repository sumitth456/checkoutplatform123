<?php

namespace Tests\Feature\Email;

use App\Models\EmailLog;
use App\Models\EmailQueueItem;
use App\Models\Order;
use App\Modules\Email\Enums\EmailLogStatus;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Order\Events\OrderPlaced;
use Database\Seeders\EmailModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_placed_event_queues_order_confirmation_email(): void
    {
        $this->seed(EmailModuleSeeder::class);
        Mail::fake();

        $order = Order::factory()->create([
            'customer_email' => 'sumitthakur8313@gmail.com',
            'order_number' => 'ORD-E2E-001',
        ]);

        event(new OrderPlaced($order));

        $item = EmailQueueItem::query()->where('recipient_email', 'sumitthakur8313@gmail.com')->first();
        $this->assertNotNull($item);

        if ($item->status !== EmailQueueStatus::Sent) {
            app(\App\Modules\Email\Services\EmailDispatchService::class)->processQueueItem($item);
            $item->refresh();
        }

        $this->assertSame(EmailQueueStatus::Sent, $item->status);

        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => 'sumitthakur8313@gmail.com',
            'status' => EmailLogStatus::Sent->value,
        ]);

        $log = EmailLog::query()->where('recipient_email', 'sumitthakur8313@gmail.com')->firstOrFail();
        $this->assertStringContainsString('ORD-E2E-001', $log->subject);
        $this->assertStringNotContainsString('{{order.number}}', (string) $log->html_body);
    }
}
