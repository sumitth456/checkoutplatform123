<?php

namespace Tests\Unit\Product;

use App\Modules\Product\Import\Services\ProductImportValidationService;
use Tests\TestCase;

class ProductImportValidationServiceTest extends TestCase
{
    private ProductImportValidationService $validation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validation = new ProductImportValidationService;
    }

    public function test_missing_sku_is_reported(): void
    {
        $result = $this->validation->validate([
            [
                '_row_number' => 2,
                'sku' => '',
                'name' => 'Sample',
                'price' => '10.00',
            ],
        ]);

        $this->assertCount(1, $result['errors']);
        $this->assertSame('missing_sku', $result['errors'][0]['code']);
        $this->assertSame([], $result['products']);
    }

    public function test_duplicate_variant_sku_is_reported(): void
    {
        $result = $this->validation->validate([
            [
                '_row_number' => 2,
                'sku' => 'PARENT-1',
                'name' => 'Parent',
                'price' => '19.99',
                'variant_sku' => 'VAR-1',
                'variant' => 'Small',
            ],
            [
                '_row_number' => 3,
                'sku' => 'PARENT-1',
                'name' => 'Parent',
                'price' => '19.99',
                'variant_sku' => 'VAR-1',
                'variant' => 'Large',
            ],
        ]);

        $this->assertTrue(
            collect($result['errors'])->contains(fn (array $error): bool => $error['code'] === 'duplicate_variant_sku'),
        );
        $this->assertSame([], $result['products']);
    }

    public function test_invalid_image_url_is_reported(): void
    {
        $result = $this->validation->validate([
            [
                '_row_number' => 2,
                'sku' => 'SKU-100',
                'name' => 'Photo Product',
                'price' => '12.50',
                'image_url' => 'not-a-valid-url',
            ],
        ]);

        $this->assertTrue(
            collect($result['errors'])->contains(
                fn (array $error): bool => $error['code'] === 'invalid_image_url' && $error['field'] === 'image_url',
            ),
        );
        $this->assertSame([], $result['products']);
    }

    public function test_valid_rows_group_variants_by_product_sku(): void
    {
        $result = $this->validation->validate([
            [
                '_row_number' => 2,
                'sku' => 'GROUP-1',
                'name' => 'Grouped Product',
                'price' => '25.00',
                'variant_sku' => 'GROUP-1-S',
                'variant' => 'Small',
            ],
            [
                '_row_number' => 3,
                'sku' => 'GROUP-1',
                'name' => 'Grouped Product',
                'price' => '25.00',
                'variant_sku' => 'GROUP-1-L',
                'variant' => 'Large',
            ],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertArrayHasKey('GROUP-1', $result['products']);
        $this->assertCount(2, $result['products']['GROUP-1']['variants']);
    }
}
