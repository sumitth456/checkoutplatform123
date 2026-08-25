<?php

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailQueueItem;
use App\Modules\Email\Enums\EmailQueueStatus;

echo "=== Email Providers ===\n";
foreach (EmailProvider::query()->orderBy('priority')->get() as $p) {
    echo sprintf(
        "id=%d name=%s type=%s status=%s default=%s from=%s api_key=%s\n",
        $p->id,
        $p->name,
        $p->provider_type->value,
        $p->status->value,
        $p->is_default ? 'yes' : 'no',
        $p->from_email,
        filled($p->api_key) ? 'set('.strlen((string) $p->getRawOriginal('api_key') ?: 'enc').')' : 'missing',
    );
}

echo "\n=== Recent queue items ===\n";
foreach (EmailQueueItem::query()->latest('id')->limit(5)->get() as $item) {
    echo sprintf(
        "id=%d status=%s to=%s error=%s\n",
        $item->id,
        $item->status->value,
        $item->recipient_email,
        $item->last_error ?? '-',
    );
}

echo "\n=== Recent logs ===\n";
foreach (EmailLog::query()->latest('id')->limit(5)->get() as $log) {
    echo sprintf(
        "id=%d status=%s to=%s response=%s\n",
        $log->id,
        $log->status->value,
        $log->recipient_email,
        substr(json_encode($log->provider_response), 0, 200),
    );
}
