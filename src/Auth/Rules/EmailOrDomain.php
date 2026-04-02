<?php

namespace Cronqvist\Api\Auth\SSO\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EmailOrDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL);
        $isDomain = filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);

        if(!$isEmail && !$isDomain) {
            $fail('The :attribute must be a valid email address or domain name.');
        }
    }
}