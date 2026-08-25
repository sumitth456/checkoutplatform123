<?php

namespace App\Modules\LandingPage\Http\Controllers;

use App\Models\LandingPageAsset;
use App\Modules\LandingPage\Services\LandingPageAssetService;
use Illuminate\Http\Request;

class LandingPageAssetDeliveryController
{
    public function __invoke(Request $request, LandingPageAsset $asset, LandingPageAssetService $assets): mixed
    {
        if ($asset->is_protected) {
            $referer = parse_url((string) $request->headers->get('referer', ''), PHP_URL_HOST);
            $refererHost = is_string($referer) ? $referer : null;

            if (! $request->hasValidSignature() && ! $assets->allowsPublicDelivery($asset, $refererHost)) {
                abort(403, 'Protected asset');
            }
        }

        return $assets->stream($asset);
    }
}
