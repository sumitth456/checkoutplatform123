<?php

namespace Tests\Unit\LandingPage;

use App\Modules\LandingPage\Services\LandingPageVariableReplacer;
use Tests\TestCase;

class LandingPageVariableReplacerLegacyTest extends TestCase
{
    public function test_replaces_legacy_checkoutchamp_tokens(): void
    {
        $html = '<span>${{prd1-prc}}</span><img src="{{logo-main}}" /><p>{{csEmail}}</p><input data-id="{{m-prd1-white-sm-id}}" />';

        $context = [
            'campaign' => [
                'uuid' => 'camp-uuid-123',
                'name' => 'Test Campaign',
                'store_name' => 'Patriot Store',
            ],
            'products' => [[
                'name' => 'Patriotic Tee',
                'variants' => [[
                    'uuid' => 'variant-sm-uuid',
                    'sku' => 'TEST-001-SMALL',
                    'name' => 'Small',
                    'size' => 'Small',
                    'price' => '9.99',
                ]],
            ]],
            'merchandising' => ['order_bumps' => []],
        ];

        $result = app(LandingPageVariableReplacer::class)->replace($html, $context);

        $this->assertStringContainsString('$9.99', $result);
        $this->assertStringContainsString('@example.com', $result);
        $this->assertStringContainsString('variant-sm-uuid', $result);
        $this->assertStringNotContainsString('{{prd1-prc}}', $result);
    }

    public function test_replaces_moment_tokens(): void
    {
        $html = '<option value="{{moment \'YYYY\'}}">{{moment \'next year\' \'YYYY\'}}</option>';

        $result = app(LandingPageVariableReplacer::class)->replace($html, [
            'campaign' => ['uuid' => 'x'],
            'products' => [['name' => 'P', 'variants' => [['uuid' => 'v', 'price' => '1.00', 'name' => 'S', 'size' => 'Small']]]],
            'merchandising' => ['order_bumps' => []],
        ]);

        $this->assertStringNotContainsString('{{moment', $result);
        $this->assertMatchesRegularExpression('/\d{4}/', $result);
    }
}
