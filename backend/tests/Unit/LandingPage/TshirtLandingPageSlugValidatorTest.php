<?php

namespace Tests\Unit\LandingPage;

use App\Modules\LandingPage\Support\TshirtLandingPageReservedSlugs;
use App\Modules\LandingPage\Support\TshirtLandingPageSlugValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TshirtLandingPageSlugValidatorTest extends TestCase
{
    #[Test]
    public function it_normalizes_slug_to_lowercase_and_hyphens(): void
    {
        $this->assertSame('james-cook', TshirtLandingPageSlugValidator::normalize('James Cook'));
        $this->assertSame('another-shirt', TshirtLandingPageSlugValidator::normalize('Another-Shirt'));
    }

    #[Test]
    public function it_rejects_invalid_slug_formats(): void
    {
        $this->assertFalse(TshirtLandingPageSlugValidator::isValid('James Cook'));
        $this->assertFalse(TshirtLandingPageSlugValidator::isValid('https://jamescook.com'));
        $this->assertFalse(TshirtLandingPageSlugValidator::isValid('jamescook.famteesworld.com'));
        $this->assertFalse(TshirtLandingPageSlugValidator::isValid('james/cook'));
    }

    #[Test]
    public function it_accepts_valid_slug_formats(): void
    {
        $this->assertTrue(TshirtLandingPageSlugValidator::isValid('jamescook'));
        $this->assertTrue(TshirtLandingPageSlugValidator::isValid('seahawks'));
        $this->assertTrue(TshirtLandingPageSlugValidator::isValid('another-shirt'));
        $this->assertTrue(TshirtLandingPageSlugValidator::isValid('campaign123'));
    }

    #[Test]
    public function it_rejects_reserved_slugs(): void
    {
        foreach (TshirtLandingPageReservedSlugs::all() as $reserved) {
            $this->assertFalse(TshirtLandingPageSlugValidator::isValid($reserved));
        }
    }
}
