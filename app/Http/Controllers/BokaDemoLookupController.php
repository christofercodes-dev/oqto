<?php

namespace App\Http\Controllers;

use App\Services\Bolagsverket\BolagsverketClient;
use App\Services\Bolagsverket\SniAlternativeResolver;
use App\Services\BokaDemo\LeadLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BokaDemoLookupController extends Controller
{
    public function __invoke(Request $request, LeadLogger $logger): JsonResponse
    {
        $validated = $request->validate([
            'org_number' => ['required', 'string', 'regex:/^\d{6}-?\d{4}$/'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ]);

        $utm = $request->only(['utm_source', 'utm_medium', 'utm_campaign']);

        $orgNumber = str_replace('-', '', $validated['org_number']);

        if (! $this->hasValidChecksum($orgNumber)) {
            return response()->json([
                'message' => 'Organisationsnumret ser inte ut att stämma. Kontrollera och försök igen.',
            ], 422);
        }

        $formattedOrgNumber = substr($orgNumber, 0, 6).'-'.substr($orgNumber, 6);

        if (! config('bolagsverket.client_id') || ! config('bolagsverket.client_secret')) {
            // API-uppgifter saknas ännu (registrering hos Bolagsverket pågår) -
            // faller tillbaka på det generella kontaktformuläret.
            $logger->log('org_number', $formattedOrgNumber, null, 2, (string) $request->ip(), $utm);

            return response()->json([
                'alternative' => 2,
                'org_number' => $formattedOrgNumber,
                'company_name' => null,
            ]);
        }

        $client = new BolagsverketClient(
            config('bolagsverket.client_id'),
            config('bolagsverket.client_secret'),
            config('bolagsverket.token_uri'),
            config('bolagsverket.base_uri'),
            config('bolagsverket.scope'),
        );

        $resolver = new SniAlternativeResolver(
            config('bolagsverket.sni_prefixes_alternative_1', [])
        );

        $companyName = null;

        try {
            $organisation = $client->getOrganisation($orgNumber);
            $sniCode = $resolver->extractPrimarySniCode($organisation);
            $companyName = $resolver->extractCompanyName($organisation);
            $alternative = $resolver->resolve($sniCode);
        } catch (Throwable $e) {
            Log::warning('Bolagsverket-uppslag misslyckades: '.$e->getMessage());

            // Kan inte slå upp organisationen - visa det generella
            // kontaktformuläret istället för att blockera besökaren.
            $alternative = 2;
        }

        $logger->log('org_number', $formattedOrgNumber, $companyName, $alternative, (string) $request->ip(), $utm);

        return response()->json([
            'alternative' => $alternative,
            'org_number' => $formattedOrgNumber,
            'company_name' => $companyName,
        ]);
    }

    /**
     * Kontrollerar organisationsnumrets checksiffra enligt Luhn-algoritmen.
     */
    protected function hasValidChecksum(string $orgNumber): bool
    {
        if (strlen($orgNumber) !== 10) {
            return false;
        }

        $sum = 0;

        foreach (str_split($orgNumber) as $index => $digit) {
            $digit = (int) $digit;

            if ($index % 2 === 0) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
