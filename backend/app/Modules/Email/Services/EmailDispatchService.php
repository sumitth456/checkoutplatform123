<?php

namespace App\Modules\Email\Services;

use App\Models\EmailAutomation;
use App\Models\EmailQueueItem;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Subscription;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Enums\EmailLogStatus;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Enums\EmailTrigger;
use App\Modules\Email\Jobs\ProcessEmailQueueItemJob;
use Illuminate\Database\Eloquent\Model;

final class EmailDispatchService
{
    public function __construct(
        private readonly EmailTemplateRenderer $renderer,
        private readonly EmailProviderManager $providers,
    ) {}

    public function queueTemplate(
        EmailTemplate $template,
        string $recipientEmail,
        ?string $recipientName = null,
        ?Model $context = null,
        ?EmailAutomation $automation = null,
        ?string $idempotencyKey = null,
    ): EmailQueueItem {
        if (! $template->is_active) {
            throw new \RuntimeException("Email template [{$template->slug}] is disabled.");
        }

        $rendered = $this->renderer->render($template, $context);
        $key = $idempotencyKey ?? $this->buildIdempotencyKey($template, $recipientEmail, $context, $automation);

        $existing = EmailQueueItem::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        $delayMinutes = (int) ($automation?->delay_minutes ?? 0);

        $item = EmailQueueItem::query()->create([
            'email_template_id' => $template->id,
            'email_automation_id' => $automation?->id,
            'status' => EmailQueueStatus::Queued,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $rendered['subject'],
            'html_body' => $rendered['html_body'],
            'plain_text_body' => $rendered['plain_text_body'],
            'metadata' => [
                'template_slug' => $template->slug,
                'trigger' => $automation?->trigger_event,
            ],
            'idempotency_key' => $key,
            'scheduled_at' => now()->addMinutes($delayMinutes),
            'queued_at' => now(),
            'context_type' => $context !== null ? $context::class : null,
            'context_id' => $context?->getKey(),
        ]);

        if ($delayMinutes <= 0) {
            ProcessEmailQueueItemJob::dispatch($item->id);
        }

        return $item;
    }

    public function retry(EmailQueueItem $item): EmailQueueItem
    {
        $item->update([
            'status' => EmailQueueStatus::Retry,
            'scheduled_at' => now(),
            'last_error' => null,
        ]);

        ProcessEmailQueueItemJob::dispatch($item->id)->onQueue((string) config('email.queue', 'default'));

        return $item->fresh();
    }

    public function processQueueItem(EmailQueueItem $item): EmailQueueItem
    {
        if (in_array($item->status, [EmailQueueStatus::Sent, EmailQueueStatus::Cancelled], true)) {
            return $item;
        }

        $item->update([
            'status' => EmailQueueStatus::Processing,
            'processing_at' => now(),
            'attempts' => $item->attempts + 1,
        ]);

        $replyToEmail = null;
        $replyToName = null;

        if ($item->email_provider_id) {
            $provider = $item->provider;
            $replyToEmail = $provider?->reply_to_email;
            $replyToName = $provider?->reply_to_name;
        }

        $request = new EmailSendRequest(
            toEmail: $item->recipient_email,
            toName: $item->recipient_name,
            subject: $item->subject,
            htmlBody: $item->html_body,
            plainTextBody: $item->plain_text_body,
            replyToEmail: $replyToEmail,
            replyToName: $replyToName,
            metadata: is_array($item->metadata) ? $item->metadata : [],
        );

        $result = $this->providers->sendWithFailover($request);

        $providerId = $item->email_provider_id;

        if ($result->success) {
            $providerId = $result->response['provider_id'] ?? $providerId;

            $item->update([
                'status' => EmailQueueStatus::Sent,
                'email_provider_id' => $providerId,
                'sent_at' => now(),
                'last_error' => null,
            ]);

            $item->log()->updateOrCreate([], [
                'email_template_id' => $item->email_template_id,
                'email_provider_id' => $providerId,
                'recipient_email' => $item->recipient_email,
                'recipient_name' => $item->recipient_name,
                'subject' => $item->subject,
                'status' => EmailLogStatus::Sent,
                'html_body' => $item->html_body,
                'raw_email' => $this->buildRawEmail($item),
                'provider_response' => $result->response,
                'retry_count' => max(0, $item->attempts - 1),
                'queued_at' => $item->queued_at,
                'sent_at' => now(),
                'context_type' => $item->context_type,
                'context_id' => $item->context_id,
            ]);

            return $item->fresh();
        }

        $nextStatus = $item->attempts >= $item->max_attempts
            ? EmailQueueStatus::Dead
            : EmailQueueStatus::Failed;

        $backoff = config('email.retry_backoff_minutes', [1, 5, 15, 60, 240]);
        $delayIndex = min(max(0, $item->attempts - 1), count($backoff) - 1);

        $item->update([
            'status' => $nextStatus === EmailQueueStatus::Dead ? EmailQueueStatus::Dead : EmailQueueStatus::Retry,
            'failed_at' => now(),
            'last_error' => $result->error,
            'scheduled_at' => $nextStatus === EmailQueueStatus::Dead ? null : now()->addMinutes((int) $backoff[$delayIndex]),
        ]);

        $item->log()->updateOrCreate([], [
            'email_template_id' => $item->email_template_id,
            'email_provider_id' => $providerId,
            'recipient_email' => $item->recipient_email,
            'recipient_name' => $item->recipient_name,
            'subject' => $item->subject,
            'status' => EmailLogStatus::Failed,
            'html_body' => $item->html_body,
            'provider_response' => $result->response ?? ['error' => $result->error],
            'retry_count' => $item->attempts,
            'queued_at' => $item->queued_at,
            'context_type' => $item->context_type,
            'context_id' => $item->context_id,
        ]);

        return $item->fresh();
    }

    public function sendTestEmail(
        EmailTemplate $template,
        string $recipientEmail,
        ?string $recipientName = null,
        ?Model $context = null,
    ): EmailQueueItem {
        $item = $this->queueTemplate(
            template: $template,
            recipientEmail: $recipientEmail,
            recipientName: $recipientName,
            context: $context,
            idempotencyKey: 'test:'.$template->slug.':'.md5($recipientEmail.':'.now()->timestamp),
        );

        ProcessEmailQueueItemJob::dispatchSync($item->id);

        return $item->fresh(['log']);
    }

    private function buildIdempotencyKey(
        EmailTemplate $template,
        string $recipientEmail,
        ?Model $context,
        ?EmailAutomation $automation,
    ): string {
        $parts = [
            'template' => $template->id,
            'email' => strtolower($recipientEmail),
            'automation' => $automation?->id ?? 'manual',
            'context' => $context !== null ? $context::class.':'.$context->getKey() : 'none',
        ];

        if ($context instanceof Order) {
            $parts['order_status'] = (string) ($context->status?->value ?? $context->status ?? '');
        }

        if ($context instanceof Subscription) {
            $parts['subscription_status'] = (string) ($context->status?->value ?? $context->status ?? '');
        }

        return hash('sha256', implode('|', $parts));
    }

    private function buildRawEmail(EmailQueueItem $item): string
    {
        return implode("\r\n", array_filter([
            'To: '.$item->recipient_email,
            'Subject: '.$item->subject,
            'Content-Type: text/html; charset=UTF-8',
            '',
            $item->html_body,
        ]));
    }
}
