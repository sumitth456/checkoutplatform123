<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require base_path('app/Modules/Payment/routes.php');
    require base_path('app/Modules/Order/routes.php');
    require base_path('app/Modules/Subscription/routes.php');
    require base_path('app/Modules/Product/routes.php');
    require base_path('app/Modules/Variant/routes.php');
    require base_path('app/Modules/Inventory/routes.php');
    require base_path('app/Modules/Campaign/routes.php');
    require base_path('app/Modules/Analytics/routes.php');
    require base_path('app/Modules/StripeWebhook/routes.php');
    require base_path('app/Modules/Email/routes.php');
    require base_path('app/Modules/Ach/routes.php');
});
