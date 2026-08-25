<?php

namespace App\Modules\Email\Providers;

use App\Models\EmailProvider;
use App\Modules\Email\Contracts\EmailProviderDriverInterface;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Data\EmailSendResult;
use App\Modules\Email\Enums\EmailProviderType;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

final class SmtpEmailProviderDriver implements EmailProviderDriverInterface
{
    public function send(EmailProvider $provider, EmailSendRequest $request): EmailSendResult
    {
        try {
            $this->configureMailer($provider);

            Mail::mailer('email_dynamic')->send([], [], function ($message) use ($request, $provider): void {
                $message->to($request->toEmail, $request->toName)
                    ->subject($request->subject)
                    ->from($provider->from_email, $provider->from_name)
                    ->html($request->htmlBody);

                if ($request->plainTextBody) {
                    $message->text($request->plainTextBody);
                }

                if ($request->replyToEmail) {
                    $message->replyTo($request->replyToEmail, $request->replyToName);
                }
            });

            return new EmailSendResult(success: true, messageId: (string) str()->uuid());
        } catch (\Throwable $exception) {
            return new EmailSendResult(success: false, error: $exception->getMessage());
        }
    }

    public function testConnection(EmailProvider $provider): EmailSendResult
    {
        if (blank($provider->smtp_host)) {
            return new EmailSendResult(success: false, error: 'SMTP host is required.');
        }

        if (blank($provider->username) || blank($provider->password)) {
            return new EmailSendResult(success: false, error: 'SMTP username and password are required.');
        }

        try {
            $this->configureMailer($provider);

            $transport = Mail::mailer('email_dynamic')->getSymfonyTransport();

            if (method_exists($transport, 'start')) {
                $transport->start();

                if (method_exists($transport, 'stop')) {
                    $transport->stop();
                }
            }

            return new EmailSendResult(success: true, response: [
                'message' => 'SMTP authentication successful.',
                'host' => $provider->smtp_host,
                'port' => $provider->smtp_port ?? 587,
            ]);
        } catch (\Throwable $exception) {
            return new EmailSendResult(success: false, error: $exception->getMessage());
        }
    }

    private function configureMailer(EmailProvider $provider): void
    {
        Config::set('mail.mailers.email_dynamic', [
            'transport' => 'smtp',
            'host' => $provider->smtp_host,
            'port' => $provider->smtp_port ?? 587,
            'encryption' => $provider->smtp_encryption ?? 'tls',
            'username' => $provider->username,
            'password' => $provider->password,
            'timeout' => null,
        ]);
    }
}
