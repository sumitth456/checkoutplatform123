<?php

namespace Database\Seeders;

use App\Models\EmailAutomation;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Modules\Email\Enums\EmailTrigger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmailModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultProvider();
        $templates = $this->seedTemplates();
        $this->seedAutomations($templates);
    }

    private function seedDefaultProvider(): void
    {
        if (EmailProvider::query()->exists()) {
            return;
        }

        EmailProvider::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Default SMTP',
            'provider_type' => EmailProviderType::Smtp,
            'smtp_host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
            'smtp_port' => (int) env('MAIL_PORT', 587),
            'smtp_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'from_name' => env('MAIL_FROM_NAME', config('app.name')),
            'from_email' => env('MAIL_FROM_ADDRESS', 'noreply@checkoutplatform.test'),
            'reply_to_email' => env('MAIL_REPLY_TO', env('MAIL_FROM_ADDRESS')),
            'reply_to_name' => env('MAIL_FROM_NAME', config('app.name')),
            'status' => EmailProviderStatus::Enabled,
            'priority' => 10,
            'is_default' => true,
        ]);
    }

    /**
     * @return array<string, EmailTemplate>
     */
    private function seedTemplates(): array
    {
        $definitions = [
            ['slug' => 'order-confirmation', 'name' => 'Order Confirmation', 'trigger' => EmailTrigger::OrderPlaced, 'subject' => 'Order {{order.number}} confirmed — thank you, {{customer.first_name}}!', 'body' => $this->orderConfirmationBody()],
            ['slug' => 'order-paid', 'name' => 'Order Paid', 'trigger' => EmailTrigger::OrderPaid, 'subject' => 'Payment received for order {{order.number}}', 'body' => $this->simpleBody('Your payment of {{order.total}} for order {{order.number}} was received.')],
            ['slug' => 'order-processing', 'name' => 'Order Processing', 'trigger' => EmailTrigger::OrderProcessing, 'subject' => 'We are preparing order {{order.number}}', 'body' => $this->simpleBody('Hi {{customer.first_name}}, your order {{order.number}} is being processed.')],
            ['slug' => 'order-fulfilled', 'name' => 'Order Fulfilled', 'trigger' => EmailTrigger::OrderFulfilled, 'subject' => 'Order {{order.number}} has been fulfilled', 'body' => $this->simpleBody('Your order {{order.number}} has been fulfilled.')],
            ['slug' => 'order-shipped', 'name' => 'Order Shipped', 'trigger' => EmailTrigger::OrderShipped, 'subject' => 'Your order {{order.number}} has shipped', 'body' => $this->shippingBody()],
            ['slug' => 'tracking-email', 'name' => 'Tracking Email', 'trigger' => EmailTrigger::TrackingEmail, 'subject' => 'Track shipment for order {{order.number}}', 'body' => $this->shippingBody()],
            ['slug' => 'delivered', 'name' => 'Delivered', 'trigger' => EmailTrigger::Delivered, 'subject' => 'Order {{order.number}} delivered', 'body' => $this->simpleBody('Your order {{order.number}} has been delivered. Thank you for shopping with {{company.name}}!')],
            ['slug' => 'payment-failed', 'name' => 'Payment Failed', 'trigger' => EmailTrigger::PaymentFailed, 'subject' => 'Payment failed for order {{order.number}}', 'body' => $this->simpleBody('We could not process your payment for order {{order.number}}. Please update your payment method or contact {{support.email}}.')],
            ['slug' => 'payment-success', 'name' => 'Payment Success', 'trigger' => EmailTrigger::PaymentSuccess, 'subject' => 'Payment successful — order {{order.number}}', 'body' => $this->simpleBody('Payment of {{order.total}} for order {{order.number}} was successful.')],
            ['slug' => 'subscription-created', 'name' => 'Subscription Created', 'trigger' => EmailTrigger::SubscriptionCreated, 'subject' => 'Welcome to your subscription', 'body' => $this->subscriptionBody('Your subscription is active. Next billing date: {{subscription.next_rebill}}.')],
            ['slug' => 'subscription-renewed', 'name' => 'Subscription Renewed', 'trigger' => EmailTrigger::SubscriptionRenewed, 'subject' => 'Subscription renewed', 'body' => $this->subscriptionBody('Your subscription has been renewed successfully.')],
            ['slug' => 'upcoming-rebill-reminder', 'name' => 'Upcoming Rebill Reminder', 'trigger' => EmailTrigger::UpcomingRebill, 'subject' => 'Upcoming charge on {{subscription.next_rebill}}', 'body' => $this->subscriptionBody('Your subscription will renew on {{subscription.next_rebill}} for {{subscription.amount}}.')],
            ['slug' => 'rebill-successful', 'name' => 'Rebill Successful', 'trigger' => EmailTrigger::RebillSuccess, 'subject' => 'Subscription payment successful', 'body' => $this->subscriptionBody('Your subscription payment of {{subscription.amount}} was processed successfully.')],
            ['slug' => 'rebill-failed', 'name' => 'Rebill Failed', 'trigger' => EmailTrigger::RebillFailed, 'subject' => 'Subscription payment failed', 'body' => $this->subscriptionBody('We could not process your subscription payment. Please update your billing details.')],
            ['slug' => 'retry-scheduled', 'name' => 'Retry Scheduled', 'trigger' => EmailTrigger::RetryScheduled, 'subject' => 'Payment retry scheduled', 'body' => $this->subscriptionBody('We will retry your subscription payment soon.')],
            ['slug' => 'subscription-cancelled', 'name' => 'Subscription Cancelled', 'trigger' => EmailTrigger::SubscriptionCancelled, 'subject' => 'Subscription cancelled', 'body' => $this->subscriptionBody('Your subscription has been cancelled. Contact {{support.email}} if you need help.')],
            ['slug' => 'subscription-paused', 'name' => 'Subscription Paused', 'trigger' => EmailTrigger::SubscriptionPaused, 'subject' => 'Subscription paused', 'body' => $this->subscriptionBody('Your subscription has been paused.')],
            ['slug' => 'subscription-resumed', 'name' => 'Subscription Resumed', 'trigger' => EmailTrigger::SubscriptionResumed, 'subject' => 'Subscription resumed', 'body' => $this->subscriptionBody('Your subscription has been resumed. Next billing: {{subscription.next_rebill}}.')],
            ['slug' => 'refund-requested', 'name' => 'Refund Requested', 'trigger' => EmailTrigger::RefundRequested, 'subject' => 'Refund requested for order {{order.number}}', 'body' => $this->simpleBody('We received your refund request for order {{order.number}}.')],
            ['slug' => 'refund-completed', 'name' => 'Refund Completed', 'trigger' => EmailTrigger::RefundCompleted, 'subject' => 'Refund completed for order {{order.number}}', 'body' => $this->simpleBody('Your refund for order {{order.number}} has been completed.')],
            ['slug' => 'affiliate-signup', 'name' => 'Affiliate Signup', 'trigger' => EmailTrigger::AffiliateSignup, 'subject' => 'Affiliate application received', 'body' => $this->simpleBody('Thank you for signing up as an affiliate with {{company.name}}.')],
            ['slug' => 'affiliate-approved', 'name' => 'Affiliate Approved', 'trigger' => EmailTrigger::AffiliateApproved, 'subject' => 'Your affiliate account is approved', 'body' => $this->simpleBody('Congratulations! Your affiliate account has been approved.')],
            ['slug' => 'customer-welcome', 'name' => 'Customer Welcome', 'trigger' => EmailTrigger::CustomerRegistration, 'subject' => 'Welcome to {{company.name}}', 'body' => $this->simpleBody('Hi {{customer.first_name}}, welcome to {{company.name}}!')],
            ['slug' => 'password-reset', 'name' => 'Password Reset', 'trigger' => EmailTrigger::ManualTrigger, 'subject' => 'Reset your password', 'body' => $this->simpleBody('Use the link in your account portal to reset your password. Contact {{support.email}} for help.')],
            ['slug' => 'email-verification', 'name' => 'Email Verification', 'trigger' => EmailTrigger::CustomerRegistration, 'subject' => 'Verify your email address', 'body' => $this->simpleBody('Hi {{customer.first_name}}, please verify your email address to activate your account.')],
            ['slug' => 'customer-account-created', 'name' => 'Customer Account Created', 'trigger' => EmailTrigger::CustomerRegistration, 'subject' => 'Your account has been created', 'body' => $this->simpleBody('Hi {{customer.first_name}}, your customer account at {{company.name}} is ready.')],
            ['slug' => 'call-center-invite', 'name' => 'Call Center Invite', 'trigger' => EmailTrigger::CallCenterUserCreated, 'subject' => 'Call center access invitation', 'body' => $this->simpleBody('You have been invited to join the call center platform.')],
            ['slug' => 'inventory-alert', 'name' => 'Inventory Alert', 'trigger' => EmailTrigger::InventoryLow, 'subject' => 'Low inventory alert — {{product.name}}', 'body' => $this->simpleBody('Inventory for {{product.name}} is running low.')],
            ['slug' => 'admin-notifications', 'name' => 'Admin Notifications', 'trigger' => EmailTrigger::ManualTrigger, 'subject' => 'Admin notification', 'body' => $this->simpleBody('An administrative notification was triggered in {{company.name}}.')],
        ];

        $templates = [];

        foreach ($definitions as $definition) {
            $templates[$definition['slug']] = EmailTemplate::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'category' => 'transactional',
                    'trigger_event' => $definition['trigger']->value,
                    'subject' => $definition['subject'],
                    'html_body' => $definition['body'],
                    'plain_text_body' => null,
                    'variables' => $this->defaultVariables(),
                    'is_active' => true,
                ],
            );
        }

        return $templates;
    }

    /**
     * @param  array<string, EmailTemplate>  $templates
     */
    private function seedAutomations(array $templates): void
    {
        $map = [
            EmailTrigger::OrderPlaced->value => 'order-confirmation',
            EmailTrigger::OrderPaid->value => 'order-paid',
            EmailTrigger::PaymentSuccess->value => 'payment-success',
            EmailTrigger::PaymentFailed->value => 'payment-failed',
            EmailTrigger::SubscriptionCreated->value => 'subscription-created',
            EmailTrigger::SubscriptionRenewed->value => 'subscription-renewed',
            EmailTrigger::RebillSuccess->value => 'rebill-successful',
            EmailTrigger::RebillFailed->value => 'rebill-failed',
            EmailTrigger::RetryScheduled->value => 'retry-scheduled',
            EmailTrigger::OrderProcessing->value => 'order-processing',
            EmailTrigger::OrderShipped->value => 'order-shipped',
            EmailTrigger::TrackingEmail->value => 'tracking-email',
            EmailTrigger::SubscriptionCancelled->value => 'subscription-cancelled',
            EmailTrigger::SubscriptionPaused->value => 'subscription-paused',
            EmailTrigger::SubscriptionResumed->value => 'subscription-resumed',
            EmailTrigger::RefundCompleted->value => 'refund-completed',
        ];

        foreach ($map as $trigger => $slug) {
            $template = $templates[$slug] ?? null;

            if ($template === null) {
                continue;
            }

            EmailAutomation::query()->updateOrCreate(
                [
                    'trigger_event' => $trigger,
                    'email_template_id' => $template->id,
                ],
                [
                    'name' => $template->name.' Automation',
                    'delay_minutes' => 0,
                    'priority' => 10,
                    'is_active' => true,
                ],
            );
        }
    }

    private function orderConfirmationBody(): string
    {
        return $this->wrapHtml(
            '<h1 style="margin:0 0 16px;font-size:24px;">Thank you, {{customer.first_name}}!</h1>'
            .'<p>Your order <strong>{{order.number}}</strong> was placed on {{order.date}}.</p>'
            .'<h2 style="font-size:18px;margin:24px 0 8px;">Order items</h2>{{order.items_html}}'
            .'<table style="width:100%;margin-top:16px;font-size:14px;">'
            .'<tr><td>Subtotal</td><td style="text-align:right;">{{order.subtotal}}</td></tr>'
            .'<tr><td>Discount</td><td style="text-align:right;">{{order.discount}}</td></tr>'
            .'<tr><td>Shipping</td><td style="text-align:right;">{{order.shipping}}</td></tr>'
            .'<tr><td>Tax</td><td style="text-align:right;">{{order.tax}}</td></tr>'
            .'<tr><td><strong>Total</strong></td><td style="text-align:right;"><strong>{{order.total}}</strong></td></tr>'
            .'</table>'
            .'<h2 style="font-size:18px;margin:24px 0 8px;">Billing address</h2><p>{{order.billing_address}}</p>'
            .'<h2 style="font-size:18px;margin:24px 0 8px;">Shipping address</h2><p>{{order.shipping_address}}</p>'
            .'<p><strong>Payment method:</strong> {{order.payment_method}}</p>'
            .'<p><strong>Subscription next billing:</strong> {{subscription.next_rebill}}</p>'
            .'<p style="margin-top:24px;"><a href="{{order.invoice_url}}" style="color:#2563eb;">Download invoice</a></p>'
            .'<p>{{thank_you_message}}</p>'
            .'<p>Questions? Contact us at {{support.email}}</p>'
        );
    }

    private function shippingBody(): string
    {
        return $this->wrapHtml(
            '<h1 style="margin:0 0 16px;font-size:24px;">Your order is on the way</h1>'
            .'<p>Hi {{customer.first_name}}, order <strong>{{order.number}}</strong> has shipped via {{carrier}}.</p>'
            .'<p><strong>Tracking number:</strong> {{tracking.number}}</p>'
            .'<p><a href="{{tracking.url}}" style="color:#2563eb;">Track your package</a></p>'
            .'<p>Warehouse: {{warehouse}}</p>'
        );
    }

    private function subscriptionBody(string $message): string
    {
        return $this->wrapHtml(
            '<h1 style="margin:0 0 16px;font-size:24px;">Subscription update</h1>'
            .'<p>Hi {{customer.first_name}},</p>'
            .'<p>'.$message.'</p>'
            .'<p><strong>Subscription ID:</strong> {{subscription.id}}</p>'
            .'<p><strong>Product:</strong> {{product.name}}</p>'
            .'<p>Need help? {{support.email}}</p>'
        );
    }

    private function simpleBody(string $message): string
    {
        return $this->wrapHtml(
            '<h1 style="margin:0 0 16px;font-size:24px;">{{company.name}}</h1>'
            .'<p>Hi {{customer.first_name}},</p>'
            .'<p>'.$message.'</p>'
            .'<p>Support: {{support.email}}</p>'
        );
    }

    private function wrapHtml(string $content): string
    {
        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f6f8fb;margin:0;padding:24px;">'
            .'<table role="presentation" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;width:100%;">'
            .'<tr><td style="padding:24px 24px 0;text-align:center;"><img src="{{company.logo_url}}" alt="{{company.name}}" style="max-height:48px;"></td></tr>'
            .'<tr><td style="padding:24px;">'.$content.'</td></tr>'
            .'<tr><td style="padding:16px 24px;background:#f3f4f6;font-size:12px;color:#6b7280;text-align:center;">&copy; {{company.name}}</td></tr>'
            .'</table></body></html>';
    }

    /**
     * @return list<string>
     */
    private function defaultVariables(): array
    {
        return [
            'customer.first_name', 'customer.last_name', 'customer.email', 'customer.phone',
            'order.number', 'order.date', 'order.total', 'order.tax', 'order.shipping',
            'order.discount', 'order.status', 'product.name', 'product.variant',
            'product.color', 'product.size', 'subscription.id', 'subscription.next_rebill',
            'tracking.number', 'tracking.url', 'carrier', 'warehouse', 'company.name',
            'company.logo_url', 'support.email',
        ];
    }
}
