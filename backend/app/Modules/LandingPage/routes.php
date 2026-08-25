<?php

use App\Modules\LandingPage\Http\Controllers\LandingPageAssetDeliveryController;
use App\Modules\LandingPage\Http\Controllers\LandingPageAssetLibraryController;
use App\Modules\LandingPage\Http\Controllers\LandingPagePublicController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('landing_pages.public_path_prefix', 'offer'), '/');

Route::middleware(['web', 'throttle:120,1'])->group(function () use ($prefix): void {
    Route::get($prefix.'/assets/{asset:uuid}', LandingPageAssetDeliveryController::class)
        ->name('landing-pages.assets.deliver');

    Route::get($prefix.'/{slug}', [LandingPagePublicController::class, 'show'])
        ->name('landing-pages.show');

    Route::get($prefix.'/{slug}/checkout-context.json', [LandingPagePublicController::class, 'checkoutContext'])
        ->name('landing-pages.checkout-context');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/landing-pages/{landingPage}/preview', [LandingPagePublicController::class, 'preview'])
        ->name('landing-pages.preview');

    Route::get('admin/landing-pages/{landingPage}/builder-preview', [LandingPagePublicController::class, 'builderPreview'])
        ->name('landing-pages.builder-preview');

    Route::get('admin/landing-page-templates/{template}/preview', [LandingPagePublicController::class, 'templatePreview'])
        ->name('landing-page-templates.preview');

    Route::get('admin/landing-page-templates/{template}/builder-preview', [LandingPagePublicController::class, 'templateBuilderPreview'])
        ->name('landing-page-templates.builder-preview');

    Route::get('admin/landing-pages/{landingPage}/assets.json', LandingPageAssetLibraryController::class)
        ->name('landing-pages.assets.library');
});
