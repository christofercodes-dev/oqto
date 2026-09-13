<?php

namespace App\Services\Bolagsverket;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BolagsverketClient
{
    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $tokenUri,
        protected string $baseUri,
        protected string $scope,
    ) {
    }

    /**
     * Hämtar organisationsinformation (bl.a. SNI-koder) för ett givet
     * organisationsnummer via "organisationer"-tjänsten. Kastar
     * RuntimeException om organisationen inte kan slås upp.
     */
    public function getOrganisation(string $organisationNumber): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->post("{$this->baseUri}/organisationer", [
                'identitetsbeteckning' => $organisationNumber,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Bolagsverket svarade med fel ({$response->status()}) för {$organisationNumber}."
            );
        }

        $organisation = $response->json('organisationer.0');

        if (! $organisation || ! ($organisation['organisationsidentitet'] ?? null)) {
            throw new RuntimeException("Organisation {$organisationNumber} hittades inte.");
        }

        return $organisation;
    }

    /**
     * OAuth2 client-credentials-token, cachad tills strax innan den går ut.
     */
    protected function getAccessToken(): string
    {
        $cacheKey = 'bolagsverket.access_token.'.md5($this->clientId.$this->scope);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $response = Http::asForm()->post($this->tokenUri, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Kunde inte hämta access token från Bolagsverket.');
            }

            return $response->json('access_token');
        });
    }
}
