<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailQueueItem;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailTrigger;
use App\Modules\Email\Services\EmailAutomationService;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Email\Services\EmailProviderManager;
use App\Modules\Email\Support\KlaviyoFlowSetup;
use App\Modules\Order\Events\OrderPlaced;
use Illuminate\Support\Str;

$action = $argv[1] ?? 'inspect';

if ($action === 'inspect') {
    echo "=== Providers ===\n";
    foreach (EmailProvider::query()->orderBy('priority')->get() as $p) {
        echo json_encode([
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->provider_type->value,
            'status' => $p->status->value,
            'default' => $p->is_default,
            'from' => $p->from_email,
            'has_api_key' => filled($p->api_key),
            'smtp_host' => $p->smtp_host,
            'smtp_port' => $p->smtp_port,
            'smtp_encryption' => $p->smtp_encryption,
            'smtp_user' => $p->username,
            'has_smtp_password' => filled($p->password),
            'settings' => $p->settings,
        ], JSON_PRETTY_PRINT)."\n";
    }

    echo "\n=== Recent queue ===\n";
    foreach (EmailQueueItem::query()->latest('id')->limit(5)->get() as $item) {
        echo json_encode([
            'id' => $item->id,
            'status' => $item->status->value,
            'to' => $item->recipient_email,
            'error' => $item->last_error,
        ])."\n";
    }

    echo "\n=== Recent logs ===\n";
    foreach (EmailLog::query()->latest('id')->limit(5)->get() as $log) {
        echo json_encode([
            'id' => $log->id,
            'status' => $log->status->value,
            'to' => $log->recipient_email,
            'response' => $log->provider_response,
        ])."\n";
    }

    exit(0);
}

if ($action === 'test-smtp') {
    $provider = EmailProvider::query()
        ->where('provider_type', 'smtp')
        ->where('status', EmailProviderStatus::Enabled)
        ->orderByDesc('is_default')
        ->firstOrFail();

    $result = app(EmailProviderManager::class)->testProvider($provider);
    echo json_encode([
        'provider' => $provider->name,
        'host' => $provider->smtp_host,
        'port' => $provider->smtp_port,
        'username' => $provider->username,
        'from' => $provider->from_email,
        'success' => $result->success,
        'error' => $result->error,
        'response' => $result->response,
    ], JSON_PRETTY_PRINT)."\n";
    exit($result->success ? 0 : 1);
}

if ($action === 'test-klaviyo') {
    $provider = EmailProvider::query()->where('provider_type', 'klaviyo')->firstOrFail();
    $result = app(EmailProviderManager::class)->testProvider($provider);
    echo json_encode($result, JSON_PRETTY_PRINT)."\n";
    exit($result->success ? 0 : 1);
}

if ($action === 'set-klaviyo-default') {
    EmailProvider::query()->update(['is_default' => false, 'priority' => 100]);
    EmailProvider::query()->where('provider_type', 'klaviyo')->update([
        'is_default' => true,
        'priority' => 1,
        'status' => EmailProviderStatus::Enabled,
        'settings' => ['metric_name' => 'Checkout Platform Email'],
    ]);
    EmailProvider::query()->where('provider_type', 'smtp')->update([
        'is_default' => false,
        'priority' => 50,
    ]);
    echo "Klaviyo set as default provider\n";
    exit(0);
}

if ($action === 'klaviyo-flow-setup') {
    $provider = EmailProvider::query()->where('provider_type', 'klaviyo')->first();
    $metric = $provider ? KlaviyoFlowSetup::metricName($provider->settings) : KlaviyoFlowSetup::DEFAULT_METRIC_NAME;
    $fromEmail = $provider?->from_email ?? 'noreply@example.com';
    $fromName = $provider?->from_name ?? 'Checkout Platform';

    echo strip_tags(str_replace(['</li>', '</p>', '</ol>', '<br>'], ["\n", "\n\n", "\n", "\n"], KlaviyoFlowSetup::instructions($metric, $fromEmail, $fromName)));
    echo "\n\n=== Sample event properties ===\n";
    echo json_encode(KlaviyoFlowSetup::eventProperties(
        subject: 'Test order confirmation',
        htmlBody: '<p>Hello <strong>Sumit</strong>, your order is confirmed.</p>',
        plainTextBody: 'Hello Sumit, your order is confirmed.',
        fromEmail: $fromEmail,
        fromName: $fromName,
        metadata: ['template_slug' => 'order-confirmation', 'trigger' => 'order.placed'],
    ), JSON_PRETTY_PRINT)."\n";
    exit(0);
}

if ($action === 'send-test') {
    $email = $argv[2] ?? 'sumitthakur8313@gmail.com';
    $template = EmailTemplate::query()->where('slug', 'order-confirmation')->firstOrFail();
    $dispatch = app(EmailDispatchService::class);

    $item = $dispatch->sendTestEmail(
        template: $template,
        recipientEmail: $email,
        recipientName: 'Sumit Thakur',
    );

    echo json_encode([
        'queue_status' => $item->status->value,
        'log_status' => $item->log?->status?->value,
        'error' => $item->last_error,
        'provider_response' => $item->log?->provider_response,
    ], JSON_PRETTY_PRINT)."\n";

    exit($item->status->value === 'sent' ? 0 : 1);
}

if ($action === 'fix-gmail-provider') {
    $provider = EmailProvider::query()
        ->where('provider_type', 'smtp')
        ->orderByDesc('is_default')
        ->firstOrFail();

    $email = $provider->username ?: 'sumitthakur8313@gmail.com';

    $provider->update([
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'from_email' => $email,
        'from_name' => $provider->from_name ?: config('app.name', 'Checkout Platform'),
        'status' => EmailProviderStatus::Enabled,
        'is_default' => true,
    ]);

    echo json_encode([
        'updated' => true,
        'host' => $provider->smtp_host,
        'username' => $provider->username,
        'from_email' => $provider->from_email,
    ], JSON_PRETTY_PRINT)."\n";
    exit(0);
}

if ($action === 'export-mail-env') {
    $provider = EmailProvider::query()
        ->where('provider_type', 'smtp')
        ->where('status', EmailProviderStatus::Enabled)
        ->orderByDesc('is_default')
        ->firstOrFail();

    $lines = [
        'MAIL_MAILER=smtp',
        'MAIL_HOST='.($provider->smtp_host ?: 'smtp.gmail.com'),
        'MAIL_PORT='.($provider->smtp_port ?: 587),
        'MAIL_ENCRYPTION='.($provider->smtp_encryption ?: 'tls'),
        'MAIL_USERNAME='.$provider->username,
        'MAIL_PASSWORD='.$provider->password,
        'MAIL_FROM_ADDRESS='.$provider->from_email,
        'MAIL_FROM_NAME="'.str_replace('"', '\\"', (string) $provider->from_name).'"',
    ];

    echo implode("\n", $lines)."\n";
    exit(0);
}

if ($action === 'verify-mail-config') {
    $provider = EmailProvider::query()
        ->where('provider_type', 'smtp')
        ->where('status', EmailProviderStatus::Enabled)
        ->orderByDesc('is_default')
        ->first();

    $smtpTest = $provider
        ? app(EmailProviderManager::class)->testProvider($provider)
        : null;

    echo json_encode([
        'laravel' => [
            'default_mailer' => config('mail.default'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_port' => config('mail.mailers.smtp.port'),
            'smtp_encryption' => config('mail.mailers.smtp.encryption') ?? env('MAIL_ENCRYPTION'),
            'smtp_username' => config('mail.mailers.smtp.username'),
            'smtp_password_set' => filled(config('mail.mailers.smtp.password')),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ],
        'provider' => $provider ? [
            'name' => $provider->name,
            'host' => $provider->smtp_host,
            'port' => $provider->smtp_port,
            'username' => $provider->username,
            'from_email' => $provider->from_email,
            'password_set' => filled($provider->password),
        ] : null,
        'smtp_auth' => $smtpTest ? [
            'success' => $smtpTest->success,
            'error' => $smtpTest->error,
        ] : null,
    ], JSON_PRETTY_PRINT)."\n";

    exit($smtpTest?->success ? 0 : 1);
}

if ($action === 'trigger-order-email') {
    $email = $argv[2] ?? 'sumitthakur8313@gmail.com';
    $orderNumber = 'ORD-E2E-'.now()->format('YmdHis');

    $customer = Customer::query()->firstOrCreate(
        ['email' => $email],
        [
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Sumit',
            'last_name' => 'Thakur',
        ],
    );

    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'order_number' => $orderNumber,
        'customer_id' => $customer->id,
        'customer_email' => $email,
        'status' => 'completed',
        'payment_status' => 'paid',
        'fraud_status' => 'clear',
        'fulfillment_status' => 'unfulfilled',
        'currency' => 'USD',
        'subtotal' => 99.00,
        'tax_total' => 0,
        'shipping_total' => 0,
        'discount_total' => 0,
        'grand_total' => 99.00,
        'placed_at' => now(),
        'paid_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'name' => 'E2E Test Product',
        'quantity' => 1,
        'unit_price' => 99.00,
        'total' => 99.00,
        'options' => ['color' => 'Blue'],
    ]);

    $order = $order->fresh(['items', 'customer']);

    app(EmailAutomationService::class)->trigger(EmailTrigger::OrderPlaced, $order);

    $item = EmailQueueItem::query()
        ->where('recipient_email', $email)
        ->latest('id')
        ->first();

    if ($item === null) {
        echo json_encode([
            'order_created' => true,
            'order_number' => $orderNumber,
            'email_queued' => false,
            'error' => 'No queue item created for order confirmation automation.',
        ], JSON_PRETTY_PRINT)."\n";
        exit(1);
    }

    if ($item->status->value !== 'sent') {
        app(EmailDispatchService::class)->processQueueItem($item->fresh());
        $item->refresh();
    }

    echo json_encode([
        'order_created' => true,
        'order_id' => $order->id,
        'order_number' => $orderNumber,
        'order_exists' => Order::query()->where('order_number', $orderNumber)->exists(),
        'queue_item_id' => $item->id,
        'queue_status' => $item->status->value,
        'log_status' => $item->log?->status?->value,
        'subject' => $item->subject,
        'error' => $item->last_error,
        'provider' => $item->log?->provider_response['provider_name'] ?? null,
    ], JSON_PRETTY_PRINT)."\n";

    exit($item->status->value === 'sent' ? 0 : 1);
}

if ($action === 'verify-order-email-resilience') {
    $email = 'resilience-test-'.Str::lower(Str::random(6)).'@example.com';
    $orderNumber = 'ORD-RES-'.now()->format('YmdHis');

    $customer = Customer::query()->create([
        'uuid' => (string) Str::uuid(),
        'email' => $email,
        'first_name' => 'Resilience',
        'last_name' => 'Test',
    ]);

    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'order_number' => $orderNumber,
        'customer_id' => $customer->id,
        'customer_email' => $email,
        'status' => 'completed',
        'payment_status' => 'paid',
        'fraud_status' => 'clear',
        'fulfillment_status' => 'unfulfilled',
        'currency' => 'USD',
        'subtotal' => 1.00,
        'grand_total' => 1.00,
        'placed_at' => now(),
        'paid_at' => now(),
    ]);

    event(new OrderPlaced($order->fresh()));

    echo json_encode([
        'order_created' => Order::query()->where('order_number', $orderNumber)->exists(),
        'order_number' => $orderNumber,
        'note' => 'Order persisted before async email listener; email failures do not roll back the order.',
    ], JSON_PRETTY_PRINT)."\n";
    exit(0);
}

if ($action === 'mail-config') {
    echo json_encode([
        'mail_host' => config('mail.mailers.smtp.host'),
        'mail_port' => config('mail.mailers.smtp.port'),
        'mail_username_set' => filled(config('mail.mailers.smtp.username')),
        'mail_from' => config('mail.from.address'),
    ], JSON_PRETTY_PRINT)."\n";
    exit(0);
}

echo "Usage: php scripts/email-admin.php inspect|fix-gmail-provider|export-mail-env|verify-mail-config|test-smtp|test-klaviyo|set-klaviyo-default|klaviyo-flow-setup|send-test|trigger-order-email|verify-order-email-resilience|mail-config [email]\n";
exit(1);
