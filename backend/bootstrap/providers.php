<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\AffiliatePanelProvider::class,
    App\Modules\Product\ProductServiceProvider::class,
    App\Modules\Variant\VariantServiceProvider::class,
    App\Modules\Inventory\InventoryServiceProvider::class,
    App\Modules\Payment\PaymentServiceProvider::class,
    App\Modules\Subscription\SubscriptionServiceProvider::class,
    App\Modules\StripeWebhook\StripeWebhookServiceProvider::class,
    App\Modules\Analytics\AnalyticsServiceProvider::class,
    App\Modules\Admin\AdminServiceProvider::class,
    App\Modules\Order\OrderServiceProvider::class,
    App\Modules\Campaign\CampaignServiceProvider::class,
    App\Modules\LandingPage\LandingPageServiceProvider::class,
    App\Modules\Affiliate\AffiliateServiceProvider::class,
    App\Modules\Fulfillment\FulfillmentServiceProvider::class,
    App\Modules\ShipStation\ShipStationServiceProvider::class,
    App\Modules\Email\EmailServiceProvider::class,
    App\Modules\Ach\AchServiceProvider::class,
];
