<?php

namespace App\Modules\Email\Providers;

use App\Models\EmailProvider;
use App\Modules\Email\Contracts\EmailProviderDriverInterface;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Data\EmailSendResult;
use App\Modules\Email\Enums\EmailProviderType;
use Illuminate\Support\Facades\Http;

final class HttpEmailProviderDriver implements EmailProviderDriverInterface
{
    public function send(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        try {
            return match ($provider->provider_type) {
                EmailProviderType::SendGrid => $this->sendSendGrid($provider, $request),
                EmailProviderType::Mailgun => $this->sendMailgun($provider, $request),
                EmailProviderType::Postmark => $this->sendPostmark($provider, $request),
                EmailProviderType::Mailjet => $this->sendMailjet($provider, $request),
                EmailProviderType::Ses => $this->sendSes($provider, $request),
                default => new EmailSendResult(success: false, error: 'Unsupported HTTP provider type'),
            };
        } catch (\Throwable $exception) {
            return new EmailSendResult(success: false, error: $exception->getMessage());
        }
    }

    public function testConnection(EmailProvider $provider): EmailSendResult
    {
        if (blank($provider->api_key)) {
            return new EmailSendResult(success: false, error: 'API key is required.');
        }

        if ($provider->provider_type === EmailProviderType::Mailjet) {
            return $this->testMailjetConnection($provider);
        }

        return new EmailSendResult(success: true, response: ['message' => 'API credentials present']);
    }

    private function sendSendGrid(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        $payload = [
            'personalizations' => [[
                'to' => [['email' => $request->toEmail, 'name' => $request->toName]],
                'subject' => $request->subject,
            ]],
            'from' => ['email' => $provider->from_email, 'name' => $provider->from_name],
            'content' => array_values(array_filter([
                ['type' => 'text/plain', 'value' => $request->plainTextBody ?: strip_tags($request->htmlBody)],
                ['type' => 'text/html', 'value' => $request->htmlBody],
            ])),
        ];

        if ($request->replyToEmail) {
            $payload['reply_to'] = ['email' => $request->replyToEmail, 'name' => $request->replyToName];
        }

        $response = Http::withToken((string) $provider->api_key)
            ->post('https://api.sendgrid.com/v3/mail/send', $payload);

        if ($response->successful() || $response->status() === 202) {
            return new EmailSendResult(
                success: true,
                messageId: $response->header('X-Message-Id'),
                response: ['status' => $response->status()],
            );
        }

        return new EmailSendResult(success: false, error: $response->body(), response: ['status' => $response->status()]);
    }

    private function sendMailgun(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        $domain = (string) ($provider->settings['domain'] ?? $provider->smtp_host ?? '');

        if ($domain === '') {
            return new EmailSendResult(success: false, error: 'Mailgun domain is required in settings.domain or SMTP host.');
        }

        $response = Http::withBasicAuth('api', (string) $provider->api_key)
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", array_filter([
                'from' => "{$provider->from_name} <{$provider->from_email}>",
                'to' => $request->toName ? "{$request->toName} <{$request->toEmail}>" : $request->toEmail,
                'subject' => $request->subject,
                'html' => $request->htmlBody,
                'text' => $request->plainTextBody,
                'h:Reply-To' => $request->replyToEmail,
            ]));

        if ($response->successful()) {
            $json = $response->json();

            return new EmailSendResult(
                success: true,
                messageId: $json['id'] ?? null,
                response: $json,
            );
        }

        return new EmailSendResult(success: false, error: $response->body(), response: ['status' => $response->status()]);
    }

    private function sendPostmark(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        $response = Http::withHeaders([
            'X-Postmark-Server-Token' => (string) $provider->api_key,
            'Accept' => 'application/json',
        ])->post('https://api.postmarkapp.com/email', array_filter([
            'From' => "{$provider->from_name} <{$provider->from_email}>",
            'To' => $request->toName ? "{$request->toName} <{$request->toEmail}>" : $request->toEmail,
            'Subject' => $request->subject,
            'HtmlBody' => $request->htmlBody,
            'TextBody' => $request->plainTextBody,
            'ReplyTo' => $request->replyToEmail,
        ]));

        if ($response->successful()) {
            $json = $response->json();

            return new EmailSendResult(
                success: true,
                messageId: $json['MessageID'] ?? null,
                response: $json,
            );
        }

        return new EmailSendResult(success: false, error: $response->body(), response: ['status' => $response->status()]);
    }

    private function sendMailjet(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        if (blank($provider->secret_key)) {
            return new EmailSendResult(success: false, error: 'Mailjet secret key is required.');
        }

        $message = [
            'From' => ['Email' => $provider->from_email, 'Name' => $provider->from_name],
            'To' => [['Email' => $request->toEmail, 'Name' => $request->toName ?: $request->toEmail]],
            'Subject' => $request->subject,
            'HTMLPart' => $request->htmlBody,
        ];

        if (filled($request->plainTextBody)) {
            $message['TextPart'] = $request->plainTextBody;
        } elseif (filled($request->htmlBody)) {
            $message['TextPart'] = trim(strip_tags($request->htmlBody));
        }

        if ($request->replyToEmail) {
            $message['ReplyTo'] = [
                'Email' => $request->replyToEmail,
                'Name' => $request->replyToName ?: $request->replyToEmail,
            ];
        }

        $response = Http::timeout(30)
            ->withBasicAuth((string) $provider->api_key, (string) $provider->secret_key)
            ->acceptJson()
            ->post('https://api.mailjet.com/v3.1/send', [
                'Messages' => [$message],
            ]);

        if ($response->successful()) {
            $json = $response->json();

            return new EmailSendResult(
                success: true,
                messageId: (string) data_get($json, 'Messages.0.To.0.MessageID', data_get($json, 'Messages.0.MessageID')),
                response: array_merge(is_array($json) ? $json : [], [
                    'provider' => EmailProviderType::Mailjet->value,
                    'http_status' => $response->status(),
                ]),
            );
        }

        return new EmailSendResult(
            success: false,
            error: $this->formatMailjetError($response->json(), $response->body(), $response->status()),
            response: ['status' => $response->status(), 'provider' => EmailProviderType::Mailjet->value],
        );
    }

    private function testMailjetConnection(EmailProvider $provider): EmailSendResult
    {
        if (blank($provider->secret_key)) {
            return new EmailSendResult(success: false, error: 'Mailjet secret key is required.');
        }

        $response = Http::timeout(15)
            ->withBasicAuth((string) $provider->api_key, (string) $provider->secret_key)
            ->acceptJson()
            ->get('https://api.mailjet.com/v3/REST/user');

        if ($response->successful()) {
            return new EmailSendResult(
                success: true,
                response: [
                    'message' => 'Mailjet API credentials verified',
                    'http_status' => $response->status(),
                ],
            );
        }

        return new EmailSendResult(
            success: false,
            error: $this->formatMailjetError($response->json(), $response->body(), $response->status()),
            response: ['http_status' => $response->status()],
        );
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function formatMailjetError(?array $json, string $body, int $status): string
    {
        $message = data_get($json, 'ErrorMessage')
            ?? data_get($json, 'Messages.0.Errors.0.ErrorMessage')
            ?? data_get($json, 'Messages.0.Status');

        if (filled($message)) {
            return "Mailjet API error (HTTP {$status}): {$message}";
        }

        return "Mailjet API error (HTTP {$status})";
    }

    private function sendSes(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        if (filled($provider->smtp_host)) {
            return app(SmtpEmailProviderDriver::class)->send($provider, $request);
        }

        return new EmailSendResult(
            success: false,
            error: 'Configure Amazon SES with SMTP host/port or use the SMTP provider type.',
        );
    }
}
