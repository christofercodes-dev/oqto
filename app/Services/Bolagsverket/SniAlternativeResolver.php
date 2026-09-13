<?php

namespace App\Services\Bolagsverket;

class SniAlternativeResolver
{
    /**
     * @param  array<int, string>  $prefixesForAlternativeOne
     */
    public function __construct(
        protected array $prefixesForAlternativeOne,
    ) {
    }

    /**
     * Avgör om organisationens primära SNI-kod ska ge alternativ 1 eller 2.
     */
    public function resolve(?string $sniCode): int
    {
        if ($sniCode === null) {
            return 2;
        }

        foreach ($this->prefixesForAlternativeOne as $prefix) {
            if (str_starts_with($sniCode, $prefix)) {
                return 1;
            }
        }

        return 2;
    }

    /**
     * Plockar ut organisationens primära SNI-kod ur Bolagsverkets svar
     * (organisationer[].naringsgrenOrganisation.sni[].kod). Listan innehåller
     * ofta tomma "pad"-poster (kod = blanksteg), dessa filtreras bort.
     */
    public function extractPrimarySniCode(array $organisation): ?string
    {
        $sniList = $organisation['naringsgrenOrganisation']['sni'] ?? [];

        $primary = collect($sniList)->first(
            fn ($entry) => trim($entry['kod'] ?? '') !== ''
        );

        return $primary ? trim($primary['kod']) : null;
    }

    /**
     * Plockar ut organisationens (senast registrerade) namn ur Bolagsverkets
     * svar (organisationer[].organisationsnamn.organisationsnamnLista[].namn).
     */
    public function extractCompanyName(array $organisation): ?string
    {
        return $organisation['organisationsnamn']['organisationsnamnLista'][0]['namn'] ?? null;
    }
}
