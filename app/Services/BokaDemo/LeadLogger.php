<?php

namespace App\Services\BokaDemo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Loggar besökare som tagit sig förbi första steget på /boka-demo (skrivit
 * in organisationsnummer eller bolagsnamn och klickat "Fortsätt") till en
 * Zapier-webhook, t.ex. för ett Google Sheet. På så sätt syns även de som
 * aldrig slutför bokningen eller formuläret.
 */
class LeadLogger
{
    /**
     * @param  'org_number'|'company_name'  $inputType
     * @param  array<string, mixed>  $utm
     */
    public function log(
        string $inputType,
        ?string $orgNumber,
        ?string $companyName,
        int $alternative,
        string $ip,
        array $utm = [],
    ): void {
        $webhookUrl = config('services.zapier.webhooks.boka_demo_lookup');

        if (! $webhookUrl) {
            return;
        }

        // Enskilda firmors organisationsnummer är personnummer (tredje
        // siffran är 0 eller 1, till skillnad från juridiska personer där
        // den är minst 2) - de loggas inte alls.
        if ($orgNumber !== null && $this->isPersonalIdentityNumber($orgNumber)) {
            return;
        }

        $identity = $orgNumber ?? mb_strtolower(trim((string) $companyName));

        if ($identity === '') {
            return;
        }

        // Samma företag loggas bara en gång per timme och IP-adress.
        $dedupeKey = 'boka-demo-lead:'.sha1($ip.'|'.$identity);

        if (! Cache::add($dedupeKey, true, now()->addHour())) {
            return;
        }

        $payload = [
            'form' => 'boka_demo_lookup',
            'submitted_at' => now()->toIso8601String(),
            'input_type' => $inputType,
            'org_number' => $orgNumber,
            'company_name' => $companyName,
            'step' => $alternative === 1 ? 'cal' : 'formular',
            'utm_source' => $utm['utm_source'] ?? null,
            'utm_medium' => $utm['utm_medium'] ?? null,
            'utm_campaign' => $utm['utm_campaign'] ?? null,
        ];

        // Skickas efter att svaret gått till besökaren, så en seg webhook
        // aldrig fördröjer sidan.
        defer(function () use ($webhookUrl, $payload) {
            try {
                Http::timeout(10)->post($webhookUrl, $payload)->throw();
            } catch (Throwable $e) {
                Log::warning('Kunde inte skicka boka-demo-lead till Zapier: '.$e->getMessage());
            }
        });
    }

    protected function isPersonalIdentityNumber(string $orgNumber): bool
    {
        $digits = preg_replace('/\D/', '', $orgNumber);

        return strlen($digits) === 10 && (int) $digits[2] < 2;
    }
}
