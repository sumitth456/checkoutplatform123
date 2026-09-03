<?php

namespace App\Modules\LandingPage\Rules;

use App\Modules\LandingPage\Support\TshirtLandingPageSlugValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class TshirtLandingPageSlugRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (TshirtLandingPageSlugValidator::validationErrors(is_string($value) ? $value : null) as $message) {
            $fail($message);
        }
    }
}
