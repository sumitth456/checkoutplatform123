<?php

namespace Tests\Unit\Email;

use App\Models\EmailProvider;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Modules\Email\Providers\HttpEmailProviderDriver;
use App\Modules\Email\Services\MailjetProviderBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailjetEmailProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mailjet_configuration_loads_from_environment(): void
    {
        config([
            'email.mailjet.api_key' => 'test-api-key',
            'email.mailjet.secret_key' => 'test-secret-key',
            'email.mailjet.from_address' => 'noreply@example.com',
            'email.mailjet.from_name' => 'Checkout Platform',
        ]);

        $bootstrap = app(MailjetProviderBootstrapService::class);

        $this->assertTrue($bootstrap->isConfigured());
    }

    #[Test]
    public function bootstrap_service_creates_default_mailjet_provider_from_environment(): void
    {
        config([
            'email.mailjet.api_key' => 'test-api-key',
            'email.mailjet.secret_key' => 'test-secret-key',
            'email.mailjet.from_address' => 'noreply@example.com',
            'email.mailjet.from_name' => 'Checkout Platform',
            'email.mailjet.default_provider' => true,
        ]);

        $provider = app(MailjetProviderBootstrapService::class)->syncFromEnvironment();

        $this->assertNotNull($provider);
        $this->assertSame(EmailProviderType::Mailjet, $provider->provider_type);
        $this->assertSame('noreply@example.com', $provider->from_email);
        $this->assertTrue($provider->is_default);
        $this->assertSame(EmailProviderStatus::Enabled, $provider->status);
    }

    #[Test]
    public function mailjet_driver_sends_transactional_email_with_expected_payload(): void
    {
        Http::fake([
            'api.mailjet.com/v3.1/send' => Http::response([
                'Messages' => [[
                    'Status' => 'success',
                    'To' => [['Email' => 'customer@example.com', 'MessageID' => 123456789]],
                ]],
            ], 200),
        ]);

        $provider = EmailProvider::query()->create([
            'name' => 'Mailjet Test',
            'provider_type' => EmailProviderType::Mailjet,
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
            'from_email' => 'noreply@example.com',
            'from_name' => 'Checkout Platform',
            'status' => EmailProviderStatus::Enabled,
            'priority' => 1,
            'is_default' => true,
        ]);

        $result = app(HttpEmailProviderDriver::class)->send(
            $provider,
            new EmailSendRequest(
                toEmail: 'customer@example.com',
                toName: 'Jane Customer',
                subject: 'Your order ORD-100 has shipped',
                htmlBody: '<p>Tracking: 782758401696</p>',
                plainTextBody: 'Tracking: 782758401696',
            ),
        );

        $this->assertTrue($result->success);
        $this->assertSame('123456789', $result->messageId);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'api.mailjet.com/v3.1/send')) {
                return false;
            }

            $message = $request->data()['Messages'][0] ?? [];

            return ($message['From']['Email'] ?? null) === 'noreply@example.com'
                && ($message['To'][0]['Email'] ?? null) === 'customer@example.com'
                && ($message['Subject'] ?? null) === 'Your order ORD-100 has shipped'
                && str_contains((string) ($message['HTMLPart'] ?? ''), '782758401696');
        });
    }

    #[Test]
    public function mailjet_connection_test_uses_user_endpoint(): void
    {
        Http::fake([
            'api.mailjet.com/v3/REST/user' => Http::response(['Count' => 1], 200),
        ]);

        $provider = EmailProvider::query()->create([
            'name' => 'Mailjet Test',
            'provider_type' => EmailProviderType::Mailjet,
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
            'from_email' => 'noreply@example.com',
            'from_name' => 'Checkout Platform',
            'status' => EmailProviderStatus::Enabled,
        ]);

        $result = app(HttpEmailProviderDriver::class)->testConnection($provider);

        $this->assertTrue($result->success);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v3/REST/user'));
    }
}
