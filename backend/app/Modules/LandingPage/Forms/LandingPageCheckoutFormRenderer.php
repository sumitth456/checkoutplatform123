<?php

namespace App\Modules\LandingPage\Forms;

use App\Models\LandingPageForm;

final class LandingPageCheckoutFormRenderer
{
    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function render(LandingPageForm $form, ?array $settings = null): string
    {
        $settings = $settings ?? $form->settings ?? [];
        $fields = $this->sortedEnabledFields($form->field_schema ?? []);

        $class = e((string) ($settings['form_css_class'] ?? 'lp-checkout-form'));
        $submitLabel = e((string) ($settings['submit_label'] ?? 'Complete checkout'));

        $html = ['<form id="checkout-form" class="'.$class.'" method="post" novalidate>'];

        foreach ($fields as $field) {
            $chunk = $this->renderField($field);
            if ($chunk !== '') {
                $html[] = $chunk;
            }
        }

        $html[] = '<button type="submit" class="lp-checkout-submit">'.$submitLabel.'</button>';
        $html[] = '</form>';

        return implode("\n", $html);
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     * @return list<array<string, mixed>>
     */
    public function sortedEnabledFields(array $schema): array
    {
        return collect($schema)
            ->filter(fn (array $field): bool => (bool) ($field['enabled'] ?? true))
            ->sortBy(fn (array $field): int => (int) ($field['sort_order'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function renderField(array $field): string
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === CheckoutFormFieldType::SectionHeading->value) {
            $label = e((string) ($field['label'] ?? ''));

            return $label !== '' ? '<h3 class="lp-form-section">'.$label.'</h3>' : '';
        }

        $name = e((string) ($field['name'] ?? ''));
        if ($name === '') {
            return '';
        }

        $label = e((string) ($field['label'] ?? $name));
        $required = ! empty($field['required']) ? ' required' : '';
        $placeholder = e((string) ($field['placeholder'] ?? ''));
        $default = e((string) ($field['default'] ?? ''));

        if ($type === CheckoutFormFieldType::Hidden->value || $type === CheckoutFormFieldType::PaymentToken->value) {
            return '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$default.'" placeholder="Payment token"'.$required.' />';
        }

        if ($type === CheckoutFormFieldType::Textarea->value) {
            return '<label class="lp-field"><span>'.$label.'</span><textarea name="'.$name.'" id="'.$name.'" placeholder="'.$placeholder.'"'.$required.'></textarea></label>';
        }

        if ($type === CheckoutFormFieldType::Select->value) {
            $source = (string) ($field['options_source'] ?? '');

            if ($source === 'campaign_variants') {
                return '<label class="lp-field"><span>'.$label.'</span><select name="'.$name.'" id="'.$name.'" data-lp-options="campaign_variants"'.$required.'><option value="">Select variant…</option></select></label>';
            }

            return '<label class="lp-field"><span>'.$label.'</span><select name="'.$name.'" id="'.$name.'"'.$required.'></select></label>';
        }

        $inputType = match ($type) {
            CheckoutFormFieldType::Email->value => 'email',
            CheckoutFormFieldType::Tel->value => 'tel',
            CheckoutFormFieldType::Number->value => 'number',
            default => 'text',
        };

        $min = isset($field['min']) ? ' min="'.(int) $field['min'].'"' : '';
        $value = $default !== '' ? ' value="'.$default.'"' : '';

        return '<label class="lp-field"><span>'.$label.'</span><input type="'.$inputType.'" name="'.$name.'" id="'.$name.'" placeholder="'.$placeholder.'"'.$required.$min.$value.' /></label>';
    }

    public function pageContainsCheckoutForm(string $html): bool
    {
        $lower = strtolower($html);

        if (str_contains($lower, 'id="checkout-form"') || str_contains($lower, "id='checkout-form'")) {
            return true;
        }

        if (preg_match('/<form\b[^>]*(id=["\'](?:order_form|pp_form)["\']|data-gjs-sf-plugin=["\']sf-konnektive["\'])/i', $html)) {
            return true;
        }

        return (bool) preg_match('/<form\b/i', $html);
    }
}
