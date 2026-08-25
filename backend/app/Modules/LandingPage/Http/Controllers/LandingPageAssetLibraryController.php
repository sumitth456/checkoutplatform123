<?php

namespace App\Modules\LandingPage\Http\Controllers;

use App\Models\LandingPage;
use App\Modules\LandingPage\Services\LandingPageAssetService;
use App\Support\Admin\AdminAuthorization;
use Illuminate\Http\JsonResponse;

class LandingPageAssetLibraryController
{
    public function __invoke(LandingPage $landingPage, LandingPageAssetService $assets): JsonResponse
    {
        abort_unless(
            AdminAuthorization::can(auth()->user(), 'landing_pages.view'),
            403,
        );

        return response()->json([
            'landing_page' => [
                'uuid' => $landingPage->uuid,
                'name' => $landingPage->name,
            ],
            'assets' => $assets->libraryForPage($landingPage)->values(),
        ]);
    }
}
