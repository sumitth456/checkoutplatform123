<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Modules\Campaign\Services\CampaignCheckoutContextService;
use App\Modules\LandingPage\Forms\LandingPageCheckoutFormRenderer;
use App\Modules\LandingPage\Services\LandingPageFormService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CampaignCheckoutWebController extends Controller
{
    public function __construct(
        private readonly CampaignCheckoutContextService $context,
        private readonly LandingPageFormService $forms,
        private readonly LandingPageCheckoutFormRenderer $formRenderer,
    ) {}

    public function show(Campaign $campaign): View|Response
    {
        $campaign = $this->context->findCheckoutCampaign($campaign->uuid);
        $campaign->loadMissing('landingPage.forms');

        $page = $campaign->landingPage;

        if ($page === null) {
            abort(404, 'This campaign has no landing page assigned.');
        }

        $checkoutContext = $this->context->build($campaign);
        $contentHtml = $this->context->renderLandingPageHtml($campaign, $page, $checkoutContext);

        $checkoutForm = $this->forms->activeCheckoutForm($page);
        $injectedFormHtml = null;

        if ($checkoutForm !== null && (bool) ($checkoutForm->settings['auto_inject'] ?? true)) {
            if (! $this->formRenderer->pageContainsCheckoutForm($contentHtml)) {
                $injectedFormHtml = $this->formRenderer->render($checkoutForm);
            }
        }

        return view('campaign-checkout.show', [
            'campaign' => $campaign,
            'page' => $page,
            'checkoutContext' => $checkoutContext,
            'contentHtml' => $contentHtml,
            'injectedFormHtml' => $injectedFormHtml,
        ]);
    }
}
