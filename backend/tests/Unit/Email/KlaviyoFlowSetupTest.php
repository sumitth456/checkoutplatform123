<?php

namespace Tests\Unit\Email;

use App\Modules\Email\Support\KlaviyoFlowSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KlaviyoFlowSetupTest extends TestCase
{
    #[Test]
    public function it_builds_klaviyo_event_properties_for_flow_templates(): void
    {
        $properties = KlaviyoFlowSetup::eventProperties(
            subject: 'Order ORD-1001 confirmed',
            htmlBody: '<p>Thank you for your order.</p>',
            plainTextBody: 'Thank you for your order.',
            fromEmail: 'noreply@shop.com',
            fromName: 'Shop',
            replyToEmail: 'support@shop.com',
            replyToName: 'Support',
            metadata: [
                'template_slug' => 'order-confirmation',
                'trigger' => 'order.placed',
            ],
        );

        $this->assertSame('Order ORD-1001 confirmed', $properties['subject']);
        $this->assertSame('<p>Thank you for your order.</p>', $properties['html_body']);
        $this->assertSame('order-confirmation', $properties['template_slug']);
        $this->assertSame('checkout_platform', $properties['source']);
        $this->assertSame('<p>Thank you for your order.</p>', $properties['extra']['html']);
        $this->assertSame('order-confirmation', $properties['extra']['template_slug']);
    }

    #[Test]
    public function it_uses_default_metric_name_when_settings_missing(): void
    {
        $this->assertSame(
            KlaviyoFlowSetup::DEFAULT_METRIC_NAME,
            KlaviyoFlowSetup::metricName(null),
        );
    }
}
