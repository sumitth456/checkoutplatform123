<?php

namespace App\Modules\Email\Support;

final class KlaviyoFlowSetup
{
    public const DEFAULT_METRIC_NAME = 'Checkout Platform Email';

    public static function metricName(?array $settings): string
    {
        return (string) ($settings['metric_name'] ?? self::DEFAULT_METRIC_NAME);
    }

    /**
     * @return array<string, mixed>
     */
    public static function eventProperties(
        string $subject,
        string $htmlBody,
        ?string $plainTextBody,
        string $fromEmail,
        string $fromName,
        ?string $replyToEmail = null,
        ?string $replyToName = null,
        array $metadata = [],
    ): array {
        $plainText = $plainTextBody ?? strip_tags($htmlBody);
        $templateSlug = (string) ($metadata['template_slug'] ?? '');
        $trigger = (string) ($metadata['trigger'] ?? '');

        return array_filter([
            'subject' => $subject,
            'html_body' => $htmlBody,
            'plain_text_body' => $plainText,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to_email' => $replyToEmail,
            'reply_to_name' => $replyToName,
            'template_slug' => $templateSlug !== '' ? $templateSlug : null,
            'trigger' => $trigger !== '' ? $trigger : null,
            'source' => 'checkout_platform',
            'extra' => array_filter([
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => $plainText,
                'template_slug' => $templateSlug !== '' ? $templateSlug : null,
                'trigger' => $trigger !== '' ? $trigger : null,
            ]),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public static function instructions(string $metricName, string $fromEmail, string $fromName): string
    {
        return <<<HTML
<div class="prose prose-sm max-w-none dark:prose-invert space-y-4">
<p>Checkout Platform sends emails by firing a Klaviyo event. Create one transactional flow to deliver all platform emails.</p>

<ol class="list-decimal pl-5 space-y-2">
<li>In Klaviyo, go to <strong>Flows → Create flow → Build your own</strong>.</li>
<li>Set trigger to metric <strong>{$metricName}</strong>. If it is missing, send a test email from this admin first — the metric is created automatically.</li>
<li>Add a single <strong>Email</strong> step (no delay).</li>
<li>Edit the email template:
<ul class="list-disc pl-5 mt-1">
<li><strong>Subject:</strong> <code>{{ event.subject }}</code></li>
<li><strong>Body (HTML block):</strong> <code>{{ event.html_body|safe }}</code></li>
<li>Optional plain-text block: <code>{{ event.plain_text_body }}</code></li>
</ul>
</li>
<li>Set the sender to <strong>{$fromName} &lt;{$fromEmail}&gt;</strong> (or your verified Klaviyo sender).</li>
<li>Switch the email to <strong>Manual</strong> mode, then click <strong>Apply for transactional status</strong> in the sidebar.</li>
<li>Set the flow to <strong>Live</strong>.</li>
</ol>

<p class="text-sm text-gray-500">Platform queue status <em>sent</em> means Klaviyo accepted the event (HTTP 202). Inbox delivery happens only after this flow is live.</p>

<p class="text-sm"><strong>Optional filters:</strong> use <code>{{ event.template_slug }}</code> or <code>{{ event.trigger }}</code> in flow trigger filters to route specific email types to different templates later.</p>
</div>
HTML;
    }
}
