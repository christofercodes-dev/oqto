<?php

namespace App\Services\Byrakartan;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Redovisningsbyråerna som använder Oqto, hämtade från Byråkartans publika API
 * (GET /byraer?system=oqto) och redo att visas på /byrakartan.
 *
 * Sidorna läser bara ur cachen. Saknas den hämtas listan av den första
 * besökaren (eller av schemaläggaren, se byrakartan:sync). Misslyckas en
 * hämtning används den senast lyckade kopian ("stale"); finns ingen är listan
 * tom, så sidan visar ett vänligt meddelande i stället för att gå sönder.
 * Efter ett misslyckat försök väntar vi en minut innan nästa, så att sidan inte
 * hamrar på API:et (och blir seg) medan det ligger nere.
 *
 * Bara publik byrådata hämtas. Resultatet har formen (lista av):
 *   slug, name, city, website (bara http/https, annars null), profile_url,
 *   rating (float eller null), rating_count, services: [namn],
 *   authorized, verified, digital, lat, lng (floats eller null)
 */
class AgencyDirectory
{
    // Versionen i nycklarna höjs när formen på de cachade byråerna ändras, så att en
    // kopia med gammal form (t.ex. utan position) aldrig läses av ny kod efter en deploy.
    protected const CACHE_KEY = 'byrakartan.agencies.v4';

    protected const STALE_KEY = 'byrakartan.agencies.v4.stale';

    protected const FAILED_KEY = 'byrakartan.agencies.v4.failed';

    /** API:ets högsta tillåtna limit. */
    protected const PAGE_SIZE = 100;

    /** Skydd mot en trasig meta som skulle ge oändligt många sidor. */
    protected const MAX_PAGES = 10;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agencies(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        if (Cache::has(self::FAILED_KEY)) {
            return Cache::get(self::STALE_KEY) ?? [];
        }

        try {
            return $this->refresh();
        } catch (Throwable $e) {
            Cache::put(self::FAILED_KEY, true, 60);

            Log::warning('Byråkartan-hämtning misslyckades, använder senaste kopian: '.$e->getMessage());

            return Cache::get(self::STALE_KEY) ?? [];
        }
    }

    /**
     * Det som redan finns i cachen (färskt eller senast kända), utan att anropa
     * API:et. Används där sidan inte får vänta på nätverket, t.ex. i sidfoten.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY) ?? Cache::get(self::STALE_KEY) ?? [];
    }

    /**
     * Hämtar om listan och ersätter cachen. Kastar vidare vid fel (anroparen
     * loggar) men lämnar då den gamla cachen orörd.
     *
     * @return array<int, array<string, mixed>>
     */
    public function refresh(): array
    {
        $agencies = $this->fetch();

        Cache::put(self::CACHE_KEY, $agencies, (int) config('services.byrakartan.cache_ttl', 1800));
        Cache::forever(self::STALE_KEY, $agencies);
        Cache::forget(self::FAILED_KEY);

        return $agencies;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(): array
    {
        $agencies = [];
        $page = 1;

        do {
            $json = Http::baseUrl(rtrim((string) config('services.byrakartan.base_url'), '/'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(6)
                ->retry(1, 300, throw: false)
                ->get('/byraer', [
                    'system' => (string) config('services.byrakartan.system', 'oqto'),
                    'sort' => 'relevans',
                    'limit' => self::PAGE_SIZE,
                    'sida' => $page,
                ])
                ->throw()
                ->json();

            if (! is_array($json) || ! is_array($json['data'] ?? null)) {
                throw new RuntimeException('Oväntat svar från Byråkartans API.');
            }

            foreach ($json['data'] as $row) {
                if (is_array($row) && ($agency = $this->normalize($row)) !== null) {
                    $agencies[] = $agency;
                }
            }

            $pages = (int) ($json['meta']['sidor'] ?? 1);
            $page++;
        } while ($page <= min($pages, self::MAX_PAGES));

        return $agencies;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function normalize(array $row): ?array
    {
        $name = $this->text($row['namn'] ?? null);

        if ($name === '') {
            return null;
        }

        $rating = $row['betyg'] ?? null;
        $rating = is_numeric($rating) && (float) $rating > 0 ? round((float) $rating, 1) : null;

        $services = collect(is_array($row['tjanster'] ?? null) ? $row['tjanster'] : [])
            ->map(fn ($service) => is_array($service)
                ? ['slug' => $this->text($service['slug'] ?? null), 'name' => $this->text($service['namn'] ?? null)]
                : ['slug' => '', 'name' => ''])
            ->filter(fn (array $service) => $service['slug'] !== '' && $service['name'] !== '')
            ->values()
            ->all();

        $images = collect(is_array($row['bilder'] ?? null) ? $row['bilder'] : [])
            ->map(fn ($url) => $this->httpUrl($url))
            ->filter()
            ->take(8)
            ->values()
            ->all();

        return [
            'slug' => $this->text($row['slug'] ?? null),
            'description' => $this->text($row['beskrivning'] ?? null),
            'about' => $this->longText($row['om'] ?? null),
            'logo' => $this->httpUrl($row['logotyp'] ?? null),
            'images' => $images,
            'street' => $this->text($row['gatuadress'] ?? null),
            'postcode' => $this->text($row['postnummer'] ?? null),
            // Telefonnummer och e-post följer aldrig med i listan: bara om de finns. Själva uppgiften hämtas först
            // när besökaren klickar (och klicket räknas hos Byråkartan).
            'has_phone' => (bool) ($row['har_telefon'] ?? false),
            'has_email' => (bool) ($row['har_epost'] ?? false),
            'name' => $name,
            'city' => $this->text($row['ort'] ?? null),
            'website' => $this->httpUrl($row['webbplats'] ?? null),
            'profile_url' => $this->httpUrl($row['profil_url'] ?? null),
            'rating' => $rating,
            'rating_count' => $rating === null ? 0 : max(0, (int) ($row['antal_betyg'] ?? 0)),
            'services' => $services,
            'authorized' => (bool) ($row['auktoriserad'] ?? false),
            'verified' => (bool) ($row['verifierad'] ?? false),
            'digital' => (bool) ($row['digital'] ?? false),
            'lat' => $this->coordinate($row['lat'] ?? null, 90),
            'lng' => $this->coordinate($row['lng'] ?? null, 180),
        ];
    }

    protected function coordinate(mixed $value, int $limit): ?float
    {
        return is_numeric($value) && abs((float) $value) <= $limit ? (float) $value : null;
    }

    /** Längre text (stycken avgränsade med tomma rader), utan taggar. */
    protected function longText(mixed $value): string
    {
        return is_string($value) ? trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', strip_tags($value)))) : '';
    }

    protected function text(mixed $value): string
    {
        return is_string($value) ? trim(strip_tags($value)) : '';
    }

    /** Bara http(s)-adresser släpps igenom, så att t.ex. javascript:-länkar aldrig hamnar i en href. */
    protected function httpUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('~^https?://[^\s<>"\']+$~i', $value) === 1 ? $value : null;
    }
}
