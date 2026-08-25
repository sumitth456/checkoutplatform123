<?php

namespace App\Modules\Email\Providers;

use App\Models\EmailProvider;
use App\Modules\Email\Contracts\EmailProviderDriverInterface;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Data\EmailSendResult;
use App\Modules\Email\Support\KlaviyoFlowSetup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class KlaviyoEmailProviderDriver implements EmailProviderDriverInterface
{
    private const API_REVISION = '2026-07-15';

    public function __construct(
        private readonly SmtpEmailProviderDriver $smtpDriver,
    ) {}

    public function send(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        if ($this->hasDirectSmtpRelay($provider)) {
            $smtpResult = $this->smtpDriver->send($provider, $request);

            if ($smtpResult->success) {
                return $smtpResult;
            }
        }

        return $this->sendEvent($provider, $request);
    }

    public function testConnection(EmailProvider $provider): EmailSendResult
    {
        if (blank($provider->api_key)) {
            return new EmailSendResult(success: false, error: 'Klaviyo API key is required.');
        }

        $response = Http::withHeaders($this->headers($provider))
            ->get('https://a.klaviyo.com/api/accounts');

        if ($response->successful()) {
            $message = 'Klaviyo API key verified.';

            if ($this->hasDirectSmtpRelay($provider)) {
                $smtpTest = $this->smtpDriver->testConnection($provider);
                $message .= $smtpTest->success
                    ? ' SMTP relay configured.'
                    : ' SMTP relay failed: '.($smtpTest->error ?? 'unknown error');
            } else {
                $message .= ' Configure SMTP relay fields for direct delivery, or set up a Klaviyo transactional flow for metric "'
                    .KlaviyoFlowSetup::metricName($provider->settings).'".';
            }

            return new EmailSendResult(success: true, response: ['message' => $message]);
        }

        return new EmailSendResult(
            success: false,
            error: $response->body(),
            response: ['status' => $response->status()],
        );
    }

    private function sendEvent(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        if (blank($provider->api_key)) {
            return new EmailSendResult(success: false, error: 'Klaviyo API key is required.');
        }

        $metricName = KlaviyoFlowSetup::metricName($provider->settings);
        $uniqueId = (string) Str::uuid();

        $payload = [
            'data' => [
                'type' => 'event',
                'attributes' => [
                    'unique_id' => $uniqueId,
                    'properties' => KlaviyoFlowSetup::eventProperties(
                        subject: $request->subject,
                        htmlBody: $request->htmlBody,
                        plainTextBody: $request->plainTextBody,
                        fromEmail: (string) $provider->from_email,
                        fromName: (string) $provider->from_name,
                        replyToEmail: $request->replyToEmail,
                        replyToName: $request->replyToName,
                        metadata: $request->metadata,
                    ),
                    'metric' => [
                        'data' => [
                            'type' => 'metric',
                            'attributes' => [
                                'name' => $metricName,
                            ],
                        ],
                    ],
                    'profile' => [
                        'data' => [
                            'type' => 'profile',
                            'attributes' => array_filter([
                                'email' => $request->toEmail,
                                'first_name' => $request->toName,
                            ]),
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders($this->headers($provider))
            ->post('https://a.klaviyo.com/api/events', $payload);

        if ($response->status() === 202 || $response->successful()) {
            return new EmailSendResult(
                success: true,
                messageId: $uniqueId,
                response: [
                    'status' => $response->status(),
                    'metric' => $metricName,
                    'delivery' => 'klaviyo_event',
                    'note' => 'Event accepted. Email delivers when a Klaviyo transactional flow exists for this metric.',
                    'body' => $response->json(),
                ],
            );
        }

        return new EmailSendResult(
            success: false,
            error: $response->body(),
            response: ['status' => $response->status(), 'body' => $response->json()],
        );
    }

    private function hasDirectSmtpRelay(EmailProvider $provider): bool
    {
        return filled($provider->smtp_host)
            && filled($provider->username)
            && filled($provider->password);
    }

    /**
     * @return array<string, string>
     */
    private function headers(EmailProvider $provider): array
    {
        return [
            'Authorization' => 'Klaviyo-API-Key '.(string) $provider->api_key,
            'revision' => self::API_REVISION,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
