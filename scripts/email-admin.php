<?php

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailQueueItem;
use App\Models\EmailTemplate;
use App\Modules\Email\Services\EmailDispatchService;

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

echo "Usage: php scripts/email-admin.php inspect|send-test [email]\n";
exit(1);
