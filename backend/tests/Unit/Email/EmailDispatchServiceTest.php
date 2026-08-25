<?php

namespace Tests\Unit\Email;

use App\Models\Customer;
use App\Models\EmailProvider;
use App\Models\EmailQueueItem;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Modules\Email\Enums\EmailLogStatus;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Email\Services\EmailTemplateRenderer;
use Database\Seeders\EmailModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_template_variables_for_orders(): void
    {
        $this->seed(EmailModuleSeeder::class);

        $customer = Customer::factory()->create([
            'first_name' => 'Sumit',
            'last_name' => 'Thakur',
            'email' => 'sumitthakur8313@gmail.com',
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'order_number' => 'ORD-1001',
            'subtotal' => 100,
            'tax_total' => 10,
            'shipping_total' => 5,
            'discount_total' => 0,
            'grand_total' => 115,
            'currency' => 'USD',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'name' => 'Test Product',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
            'options' => ['color' => 'Blue', 'size' => 'M'],
        ]);

        $template = EmailTemplate::query()->where('slug', 'order-confirmation')->firstOrFail();
        $rendered = app(EmailTemplateRenderer::class)->render($template, $order->fresh(['items', 'customer']));

        $this->assertStringContainsString('Sumit', $rendered['subject']);
        $this->assertStringContainsString('ORD-1001', $rendered['html_body']);
        $this->assertStringContainsString('Test Product', $rendered['html_body']);
        $this->assertStringContainsString('Blue', $rendered['html_body']);
        $this->assertStringNotContainsString('{{order.number}}', $rendered['html_body']);
    }

    #[Test]
    public function it_processes_queue_items_and_creates_logs(): void
    {
        $this->seed(EmailModuleSeeder::class);

        EmailProvider::query()->updateOrCreate(
            ['name' => 'Test SMTP'],
            [
                'provider_type' => EmailProviderType::Smtp,
                'from_name' => 'Checkout Platform',
                'from_email' => 'noreply@test.com',
                'status' => EmailProviderStatus::Enabled,
                'priority' => 1,
                'is_default' => true,
                'smtp_host' => 'smtp.test',
                'smtp_port' => 587,
            ],
        );

        Mail::fake();
        Bus::fake();

        $template = EmailTemplate::query()->where('slug', 'order-confirmation')->firstOrFail();

        $item = app(EmailDispatchService::class)->queueTemplate(
            template: $template,
            recipientEmail: 'sumitthakur8313@gmail.com',
            recipientName: 'Sumit Thakur',
            idempotencyKey: 'unit-test-'.uniqid(),
        );

        $processed = app(EmailDispatchService::class)->processQueueItem($item->fresh());

        $this->assertSame(EmailQueueStatus::Sent, $processed->status);
        $this->assertNotNull($processed->sent_at);
        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => 'sumitthakur8313@gmail.com',
            'status' => EmailLogStatus::Sent->value,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_emails_with_idempotency_key(): void
    {
        $this->seed(EmailModuleSeeder::class);

        Bus::fake();

        $template = EmailTemplate::query()->where('slug', 'order-confirmation')->firstOrFail();
        $dispatch = app(EmailDispatchService::class);

        $first = $dispatch->queueTemplate(
            template: $template,
            recipientEmail: 'duplicate@test.com',
            idempotencyKey: 'duplicate-key',
        );

        $second = $dispatch->queueTemplate(
            template: $template,
            recipientEmail: 'duplicate@test.com',
            idempotencyKey: 'duplicate-key',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EmailQueueItem::query()->count());
    }
}
