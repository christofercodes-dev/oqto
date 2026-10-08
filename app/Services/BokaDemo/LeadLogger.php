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
        // Loggningen är en bisak och får aldrig få besökarens uppslag att
        // misslyckas - vilket fel som än uppstår här (cache, webhook,
        // konfiguration) ska uppslaget ändå svara som vanligt.
        try {
            $this->send($inputType, $orgNumber, $companyName, $alternative, $ip, $utm);
        } catch (Throwable $e) {
            Log::warning('Kunde inte logga boka-demo-lead: '.$e->getMessage());
        }
    }

    /**
     * @param  'org_number'|'company_name'  $inputType
     * @param  array<string, mixed>  $utm
     */
    protected function send(
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
            'utm_content' => $utm['utm_content'] ?? null,
            'utm_term' => $utm['utm_term'] ?? null,
        ];

        // Vanligt anrop med kort timeout, utan defer/afterResponse: ett
        // långsamt eller trasigt Zapier kostar som mest några sekunder och
        // fångas av try/catch i log().
        Http::connectTimeout(2)->timeout(3)->post($webhookUrl, $payload)->throw();
    }

    protected function isPersonalIdentityNumber(string $orgNumber): bool
    {
        $digits = preg_replace('/\D/', '', $orgNumber);

        return strlen($digits) === 10 && (int) $digits[2] < 2;
    }
}
