<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Facades\Form;

/**
 * En e-postadress får bara anmälas en gång till investerarträffen - varken som
 * deltagare 1 eller 2 i en tidigare anmälan (jämförs utan hänsyn till stora/små bokstäver).
 */
class UniqueInvestorRsvpEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = mb_strtolower(trim((string) $value));

        if ($email === '' || ! ($form = Form::find('investor_rsvp'))) {
            return;
        }

        $exists = $form->submissions()->contains(function ($submission) use ($email) {
            return in_array($email, [
                mb_strtolower(trim((string) $submission->get('attendee1_email'))),
                mb_strtolower(trim((string) $submission->get('attendee2_email'))),
            ], true);
        });

        if ($exists) {
            $fail('Det finns redan en anmälan med den e-postadressen.');
        }
    }
}
