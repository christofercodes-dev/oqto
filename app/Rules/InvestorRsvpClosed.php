<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Anmälan till investerarträffen (25 november 2026) är stängd. Formuläret
 * "investor_rsvp" finns kvar så att svaren syns i kontrollpanelen, men tar
 * inte emot nya inskick. Sidan använder i stället "investor_followup".
 */
class InvestorRsvpClosed implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fail('Anmälan till investerarträffen är stängd.');
    }
}
