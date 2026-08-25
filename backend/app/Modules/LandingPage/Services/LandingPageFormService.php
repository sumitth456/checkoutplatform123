<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPage;
use App\Models\LandingPageForm;
use App\Modules\LandingPage\Forms\CheckoutFormFieldRegistry;
use App\Modules\LandingPage\Forms\LandingPageCheckoutFormRenderer;

final class LandingPageFormService
{
    private const CHECKOUT_FORM_KEY = 'checkout-form';

    public function __construct(
        private readonly LandingPageCheckoutFormRenderer $renderer,
    ) {}

    public function activeCheckoutForm(LandingPage $page): ?LandingPageForm
    {
        return $page->forms()
            ->where('form_key', self::CHECKOUT_FORM_KEY)
            ->where('is_active', true)
            ->first();
    }

    public function ensureDefaultCheckoutForm(LandingPage $page): LandingPageForm
    {
        $existing = $page->forms()
            ->where('form_key', self::CHECKOUT_FORM_KEY)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $page->forms()->create([
            'name' => 'Checkout',
            'form_key' => self::CHECKOUT_FORM_KEY,
            'field_schema' => CheckoutFormFieldRegistry::defaultCheckoutFields(),
            'settings' => CheckoutFormFieldRegistry::defaultSettings(),
            'is_active' => true,
        ]);
    }

    public function resetToDefaults(LandingPage $page): LandingPageForm
    {
        $form = $this->ensureDefaultCheckoutForm($page);

        $form->update([
            'field_schema' => CheckoutFormFieldRegistry::defaultCheckoutFields(),
            'settings' => CheckoutFormFieldRegistry::defaultSettings(),
        ]);

        return $form->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $settings
     */
    public function updateCheckoutForm(LandingPage $page, array $fields, array $settings): LandingPageForm
    {
        $form = $this->ensureDefaultCheckoutForm($page);

        $form->update([
            'field_schema' => $fields,
            'settings' => array_merge(
                CheckoutFormFieldRegistry::defaultSettings(),
                $form->settings ?? [],
                $settings,
            ),
        ]);

        return $form->fresh();
    }

    public function injectFormIntoPageHtml(LandingPage $page): LandingPageForm
    {
        $form = $this->ensureDefaultCheckoutForm($page);
        $formHtml = $this->renderer->render($form);
        $content = (string) ($page->content_html ?? '');

        if ($this->renderer->pageContainsCheckoutForm($content)) {
            $replaced = preg_replace(
                '/<form\b[^>]*\bid=["\']checkout-form["\'][^>]*>.*?<\/form>/is',
                $formHtml,
                $content,
                1,
            );

            $content = is_string($replaced) ? $replaced : $content;
        } else {
            $wrapper = '<div class="lp-checkout-form-wrapper">'."\n".$formHtml."\n".'</div>';
            $content = trim($content) === '' ? $wrapper : trim($content)."\n".$wrapper;
        }

        $page->update(['content_html' => $content]);

        return $form->fresh();
    }
}
