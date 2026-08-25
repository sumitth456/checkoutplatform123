<?php

namespace App\Modules\Email\Enums;

enum EmailTrigger: string
{
    case OrderPlaced = 'order_placed';
    case OrderApproved = 'order_approved';
    case OrderPaid = 'order_paid';
    case PaymentFailed = 'payment_failed';
    case PaymentSuccess = 'payment_success';
    case SubscriptionCreated = 'subscription_created';
    case SubscriptionRenewed = 'subscription_renewed';
    case UpcomingRebill = 'upcoming_rebill';
    case RebillSuccess = 'rebill_success';
    case RebillFailed = 'rebill_failed';
    case RetryScheduled = 'retry_scheduled';
    case RetrySuccess = 'retry_success';
    case RetryFailed = 'retry_failed';
    case OrderProcessing = 'order_processing';
    case OrderFulfilled = 'order_fulfilled';
    case ShipmentCreated = 'shipment_created';
    case OrderShipped = 'order_shipped';
    case TrackingEmail = 'tracking_email';
    case Delivered = 'delivered';
    case RefundRequested = 'refund_requested';
    case RefundCompleted = 'refund_completed';
    case SubscriptionCancelled = 'subscription_cancelled';
    case SubscriptionPaused = 'subscription_paused';
    case SubscriptionResumed = 'subscription_resumed';
    case CustomerRegistration = 'customer_registration';
    case AffiliateSignup = 'affiliate_signup';
    case AffiliateApproved = 'affiliate_approved';
    case CallCenterUserCreated = 'call_center_user_created';
    case InventoryLow = 'inventory_low';
    case WebhookTrigger = 'webhook_trigger';
    case ManualTrigger = 'manual_trigger';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
