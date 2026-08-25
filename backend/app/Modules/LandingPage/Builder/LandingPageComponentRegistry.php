<?php

namespace App\Modules\LandingPage\Builder;

final class LandingPageComponentRegistry
{
    /**
     * @return array<string, array{label: string, category: string, icon: string, isContainer: bool, defaultProps: array<string, mixed>}>
     */
    public static function definitions(): array
    {
        return [
            'section' => ['label' => 'Section', 'category' => 'Layout', 'icon' => 'section', 'isContainer' => true, 'defaultProps' => ['tag' => 'section']],
            'container' => ['label' => 'Container', 'category' => 'Layout', 'icon' => 'container', 'isContainer' => true, 'defaultProps' => ['maxWidth' => '1200px']],
            'row' => ['label' => 'Row', 'category' => 'Layout', 'icon' => 'row', 'isContainer' => true, 'defaultProps' => ['gap' => '16px']],
            'column' => ['label' => 'Column', 'category' => 'Layout', 'icon' => 'column', 'isContainer' => true, 'defaultProps' => ['width' => '100%']],
            'heading' => ['label' => 'Heading', 'category' => 'Content', 'icon' => 'heading', 'isContainer' => false, 'defaultProps' => ['text' => 'Your headline', 'level' => 'h2']],
            'text' => ['label' => 'Text', 'category' => 'Content', 'icon' => 'text', 'isContainer' => false, 'defaultProps' => ['text' => 'Write your paragraph here.']],
            'button' => ['label' => 'Button', 'category' => 'Content', 'icon' => 'button', 'isContainer' => false, 'defaultProps' => ['text' => 'Buy Now', 'url' => '#checkout-form', 'target' => '_self']],
            'image' => ['label' => 'Image', 'category' => 'Content', 'icon' => 'image', 'isContainer' => false, 'defaultProps' => ['src' => '', 'alt' => 'Product image']],
            'video' => ['label' => 'Video', 'category' => 'Content', 'icon' => 'video', 'isContainer' => false, 'defaultProps' => ['url' => '', 'provider' => 'youtube']],
            'divider' => ['label' => 'Divider', 'category' => 'Content', 'icon' => 'divider', 'isContainer' => false, 'defaultProps' => ['style' => 'solid']],
            'spacer' => ['label' => 'Spacer', 'category' => 'Content', 'icon' => 'spacer', 'isContainer' => false, 'defaultProps' => ['height' => '32px']],
            'icon' => ['label' => 'Icon', 'category' => 'Content', 'icon' => 'icon', 'isContainer' => false, 'defaultProps' => ['icon' => '★', 'size' => '24px']],
            'list' => ['label' => 'List', 'category' => 'Content', 'icon' => 'list', 'isContainer' => false, 'defaultProps' => ['items' => ['Benefit one', 'Benefit two', 'Benefit three'], 'ordered' => false]],
            'countdown' => ['label' => 'Countdown Timer', 'category' => 'Marketing', 'icon' => 'countdown', 'isContainer' => false, 'defaultProps' => ['endAt' => '', 'label' => 'Offer ends in']],
            'progress_bar' => ['label' => 'Progress Bar', 'category' => 'Marketing', 'icon' => 'progress', 'isContainer' => false, 'defaultProps' => ['value' => 75, 'label' => 'Stock remaining']],
            'faq' => ['label' => 'FAQ / Accordion', 'category' => 'Marketing', 'icon' => 'faq', 'isContainer' => false, 'defaultProps' => ['items' => [['q' => 'Question?', 'a' => 'Answer.']]]],
            'testimonials' => ['label' => 'Testimonials', 'category' => 'Marketing', 'icon' => 'testimonials', 'isContainer' => false, 'defaultProps' => ['items' => [['name' => 'Jane D.', 'quote' => 'Great product!', 'role' => 'Customer']]]],
            'reviews' => ['label' => 'Reviews', 'category' => 'Marketing', 'icon' => 'reviews', 'isContainer' => false, 'defaultProps' => ['rating' => 5, 'count' => 128, 'label' => 'Customer reviews']],
            'trust_badges' => ['label' => 'Trust Badges', 'category' => 'Marketing', 'icon' => 'badges', 'isContainer' => false, 'defaultProps' => ['badges' => ['Secure checkout', 'Money-back guarantee', 'Fast shipping']]],
            'guarantee_box' => ['label' => 'Guarantee Box', 'category' => 'Marketing', 'icon' => 'guarantee', 'isContainer' => false, 'defaultProps' => ['title' => '30-Day Guarantee', 'text' => 'Try it risk-free.']],
            'html_block' => ['label' => 'HTML Block', 'category' => 'Advanced', 'icon' => 'html', 'isContainer' => false, 'defaultProps' => ['html' => '<div>Custom HTML</div>']],
            'custom_css' => ['label' => 'Custom CSS', 'category' => 'Advanced', 'icon' => 'css', 'isContainer' => false, 'defaultProps' => ['css' => '/* custom styles */']],
            'custom_js' => ['label' => 'Custom JavaScript', 'category' => 'Advanced', 'icon' => 'js', 'isContainer' => false, 'defaultProps' => ['js' => '// custom script']],
        ];
    }

    public static function isContainer(string $type): bool
    {
        return (bool) (self::definitions()[$type]['isContainer'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultStyles(): array
    {
        return [
            'desktop' => [
                'color' => '#111827',
                'backgroundColor' => 'transparent',
                'fontSize' => '16px',
                'fontWeight' => '400',
                'fontFamily' => 'inherit',
                'textAlign' => 'left',
                'padding' => '12px',
                'margin' => '0',
                'width' => 'auto',
                'height' => 'auto',
                'border' => 'none',
                'borderRadius' => '0',
                'boxShadow' => 'none',
                'display' => 'block',
                'visibility' => 'visible',
                'animation' => 'none',
            ],
            'tablet' => [],
            'mobile' => [],
        ];
    }
}
