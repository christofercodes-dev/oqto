<?php

namespace App\Http\Controllers;

use App\Services\Byrakartan\AgencyDirectory;
use App\Services\Byrakartan\PostcodeLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\View\View;

/**
 * /byrakartan: redovisningsbyråerna som använder Oqto, en lightversion av Byråkartan.
 * Listan hämtas från Byråkartans publika API via App\Services\Byrakartan\AgencyDirectory
 * (cachad, tål att API:et ligger nere). All sökning sker här, på den cachade listan,
 * så att en sökning aldrig ger ett extra anrop mot API:et.
 *
 * Sökningen följer Byråkartans egen:
 *  - Hitta nära sig: "Nära mig" (webbläsarens position, ?nara=lat,lng) eller ett
 *    postnummer i sökfältet (slås upp av PostcodeLocator). Byråerna sorteras då efter
 *    avstånd och visar "12 km".
 *  - Sök på ort eller namn i fritext.
 *  - Typ av byrå: "100 % digitalt" och "auktoriserad (SRF/FAR)".
 *  - Tjänster: byrån ska erbjuda samtliga valda.
 *  - Sortering: bäst match (Byråkartans relevans), närmast, högst betyg, namn.
 *
 * Varje byrå har en egen sida (/byrakartan/{slug}), se show().
 *
 * Byråkartans egen sajt är avstängd tills vidare: länkar dit (profilsidor och
 * startsidan) visas bara när services.byrakartan.link_profiles är påslaget.
 */
class AgencyDirectoryController extends Controller
{
    /** Så många tjänster visas på kortet; resten summeras som "+ N". */
    protected const SERVICES_SHOWN = 4;

    protected const SORTS = ['relevans', 'narmast', 'betyg', 'namn'];

    public function __construct(protected AgencyDirectory $directory, protected PostcodeLocator $locator) {}

    /** Listan med sökning och karta (/byrakartan). */
    public function index(Request $request): View
    {
        $all = collect($this->directory->agencies());
        $linkProfiles = (bool) config('services.byrakartan.link_profiles');

        $services = $all->flatMap(fn (array $a) => $a['services'])->unique('slug')->sortBy('name', SORT_LOCALE_STRING)->values();
        $serviceCounts = $all->flatMap(fn (array $a) => array_column($a['services'], 'slug'))->countBy();

        // Allt som kommer från adressen valideras; okända värden ignoreras.
        $q = $this->query($request->query('q'));
        $nara = $this->coordinates($request->query('nara'));
        $wantedServices = array_values(array_intersect(
            array_map('strval', array_filter((array) $request->query('tjanst', []), 'is_scalar')),
            $services->pluck('slug')->all(),
        ));
        // Ett typfilter finns bara när minst en byrå har egenskapen, annars skulle det alltid ge noll träffar.
        $hasDigital = $all->contains(fn (array $a) => $a['digital']);
        $hasAuthorized = $all->contains(fn (array $a) => $a['authorized']);
        $digital = $hasDigital && $request->boolean('digital');
        $authorized = $hasAuthorized && $request->boolean('auktoriserad');

        // Plats: webbläsarens position, annars ett postnummer i sökfältet.
        $origin = $nara;
        $originLabel = $nara ? 'din position' : '';
        $postcodeMissed = false;

        if ($origin === null && $this->locator->looksLikePostcode($q)) {
            $place = $this->locator->lookup($q);

            if ($place) {
                $origin = $place['point'];
                $originLabel = $place['label'];
                $q = ''; // Postnumret ersätter textsökningen.
            } else {
                $postcodeMissed = true;
            }
        }

        // Sorteringen följer platsen: med en plats (postnummer eller Nära mig) sorteras listan alltid på avstånd, utan en
        // plats på Byråkartans egen ordning ("Bäst match"). Ett gammalt val i adressen (sort=relevans från en flik, ett
        // bokmärke eller en delad länk) får därför aldrig åsidosätta en ny plats.
        $sort = $request->query('sort');
        $sort = is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : null;
        $sort = $origin !== null
            ? ($sort === null || $sort === 'relevans' ? 'narmast' : $sort)
            : ($sort === null || $sort === 'narmast' ? 'relevans' : $sort);

        $needle = mb_strtolower($q);

        $results = $all
            ->filter(fn (array $a) => $needle === '' || str_contains(mb_strtolower($a['name'].' '.$a['city']), $needle))
            ->filter(fn (array $a) => ! $digital || $a['digital'])
            ->filter(fn (array $a) => ! $authorized || $a['authorized'])
            ->filter(fn (array $a) => array_diff($wantedServices, array_column($a['services'], 'slug')) === [])
            ->map(fn (array $a) => $a + ['distance' => $origin ? $this->distanceKm($origin, $a) : null])
            ->values();

        $results = $this->sorted($results, $sort);

        $items = $results->values()->map(function (array $a) {
            $shown = array_slice($a['services'], 0, self::SERVICES_SHOWN);

            return [
                'agency_url' => $this->agencyUrl($a),
                'logo' => $a['logo'] ?? null,
                'name' => $a['name'],
                'city' => $a['city'],
                'distance' => $a['distance'] === null ? null : $this->distanceLabel($a['distance']),
                'authorized' => $a['authorized'],
                'digital' => $a['digital'],
                'has_rating' => $a['rating'] !== null,
                'rating' => $a['rating'] === null ? null : number_format($a['rating'], 1, ',', ''),
                'rating_count' => $a['rating_count'],
                'services' => array_map(fn (array $s) => ['name' => $s['name']], $shown),
                'services_more' => max(0, count($a['services']) - count($shown)),
            ];
        })->all();

        // Det kartan behöver (bara publik byrådata), i samma ordning som korten. Klick på en byrå i kartan går till byråns sida.
        $agencies = $results->values()->map(fn (array $a) => [
            'name' => $a['name'],
            'city' => $a['city'],
            'url' => $this->agencyUrl($a),
            'lat' => $a['lat'] ?? null,
            'lng' => $a['lng'] ?? null,
            'km' => ($a['distance'] ?? null) === null ? null : round($a['distance'], 1),
            'rating' => $a['rating'] === null ? null : number_format($a['rating'], 1, ',', ''),
        ])->all();

        $filtered = $q !== '' || $origin !== null || $wantedServices !== [] || $digital || $authorized;

        return View::make('byraer/index')->layout('layout')->with([
            'title' => 'Byråer som använder Oqto',
            'seo_title' => 'Byråer som använder Oqto',
            'seo_description' => 'Redovisningsbyråer som arbetar i Oqto. Hitta en byrå nära dig och sök på typ av byrå och tjänster.',
            'items' => $items,
            'has_items' => $items !== [],
            'has_agencies' => $all->isNotEmpty(),
            'filtered' => $filtered,
            'total' => $all->count(),
            'shown' => count($items),
            'q' => $q,
            'nara' => $nara ? implode(',', $nara) : '',
            'has_origin' => $origin !== null,
            'origin_label' => $originLabel,
            'postcode_missed' => $postcodeMissed,
            'digital' => $digital,
            'authorized' => $authorized,
            'has_digital' => $hasDigital,
            'has_authorized' => $hasAuthorized,
            'open_services' => $wantedServices !== [],
            'service_options' => $services->map(fn (array $s) => [
                'slug' => $s['slug'],
                'label' => $s['name'],
                'count' => $serviceCounts[$s['slug']] ?? 0,
                'checked' => in_array($s['slug'], $wantedServices, true),
            ])->all(),
            'sort_options' => collect([
                'relevans' => 'Bäst match',
                'narmast' => 'Närmast',
                'betyg' => 'Högst betyg',
                'namn' => 'Namn A–Ö',
            ])->reject(fn ($label, $key) => $origin === null ? $key === 'narmast' : $key === 'relevans')
                // Utan plats är "Bäst match" standardvalet och skickas som tomt värde. Med en plats finns den inte som val,
                // eftersom en plats alltid sorterar på avstånd.
                ->map(fn ($label, $key) => [
                    'value' => $key === 'relevans' ? '' : $key,
                    'label' => $label,
                    'selected' => $key === $sort,
                ])
                ->values()->all(),
            'has_map' => $all->contains(fn (array $a) => ($a['lat'] ?? null) !== null),
            'agencies_json' => json_encode($agencies, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'map_origin' => $origin ? implode(',', $origin) : '',
            'map_token' => $this->publicMapToken(),
            'map_style' => $this->mapStyle('style', 'mapbox/streets-v12'),
            'reset_url' => '/byrakartan',
            'link_directory' => $linkProfiles ? 'https://byrakartan.se' : null,
        ]);
    }

    /**
     * En byrås egen sida (/byrakartan/{slug}), så att byrån går att länka direkt. Visar allt byrån har och döljer
     * resten. Telefonnummer och e-post ligger aldrig i sidan: de döljs bakom knappar som hämtar uppgiften först vid
     * klick, så att varje klick kan räknas hos Byråkartan (se byrakartan-agency.js). Okänd slug (borttagen byrå eller
     * en gammal länk) leder till listan.
     */
    public function show(string $slug): View|RedirectResponse
    {
        $agency = collect($this->directory->agencies())->first(fn (array $a) => $a['slug'] !== '' && $a['slug'] === $slug);

        if ($agency === null) {
            return redirect('/byrakartan');
        }

        $linkProfiles = (bool) config('services.byrakartan.link_profiles');
        $track = (bool) config('services.byrakartan.track_clicks') && $agency['slug'] !== '';
        $hasPosition = ($agency['lat'] ?? null) !== null && ($agency['lng'] ?? null) !== null;
        // Adressraden i kontaktkortet: gatuadress och "postnummer ort" (postnumret skrivs på svenskt vis, "602 23"), eller bara
        // orten om byrån saknar de två första. Orten ska alltid synas någonstans på sidan.
        $postcode = preg_replace('/^(\d{3})\s?(\d{2})$/', '$1 $2', $agency['postcode']);
        $address = array_values(array_filter([$agency['street'], trim($postcode.' '.$agency['city'])]));

        $facts = array_values(array_filter([
            $agency['rating'] !== null ? '★ '.number_format($agency['rating'], 1, ',', '').' ('.$agency['rating_count'].' omdömen)' : null,
            $agency['authorized'] ? 'Auktoriserad' : null,
            $agency['digital'] ? '100 % digital' : null,
        ]));

        return View::make('byraer/show')->layout('layout')->with([
            'title' => $agency['name'],
            'seo_title' => $agency['name'].' – byrå som använder Oqto',
            'seo_description' => $agency['name'].($agency['city'] !== '' ? ' i '.$agency['city'] : '').' är en redovisningsbyrå som arbetar i Oqto.',
            'share_image' => $agency['images'][0] ?? $agency['logo'] ?? null,
            'name' => $agency['name'],
            'city' => $agency['city'],
            'logo' => $agency['logo'],
            'lead' => $agency['description'],
            'about_paragraphs' => array_map(fn (string $p) => ['text' => $p], array_values(array_filter(preg_split('/\n{2,}/', $agency['about'])))),
            'images' => array_map(fn (string $url) => ['url' => $url], $agency['images']),
            'services' => array_map(fn (array $s) => ['name' => $s['name']], $agency['services']),
            'facts' => array_map(fn (string $f) => ['text' => $f], $facts),
            'address' => array_map(fn (string $line) => ['line' => $line], $address),
            'has_address' => $address !== [],
            // Text, tjänster och bilder ligger i en egen kolumn; saknas de bildar kontaktkortet och kartan ett par.
            'has_main' => $agency['about'] !== '' || $agency['services'] !== [] || $agency['images'] !== [],
            // Logotypen ligger överst i kontaktkortet, så kortet finns även om byrån bara har en logotyp.
            'has_contact' => $agency['logo'] !== null || $address !== [] || $agency['website'] !== null || $track && ($agency['has_phone'] || $agency['has_email']) || $linkProfiles,
            'website' => $agency['website'],
            'show_phone' => $track && $agency['has_phone'],
            'show_email' => $track && $agency['has_email'],
            'profile_url' => $linkProfiles ? $agency['profile_url'] : null,
            'has_map' => $hasPosition,
            'has_aside' => $hasPosition || $address !== [],
            'agencies_json' => json_encode($hasPosition ? [[
                'name' => $agency['name'],
                'city' => $agency['city'],
                'url' => null,
                'lat' => $agency['lat'],
                'lng' => $agency['lng'],
                'km' => null,
                'rating' => null,
            ]] : [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'map_token' => $this->publicMapToken(),
            'map_style' => $this->mapStyle('style_agency', 'mapbox/light-v11'),
            'api_base' => $track ? rtrim((string) config('services.byrakartan.base_url'), '/') : '',
            'slug' => $agency['slug'],
            'list_url' => '/byrakartan',
        ]);
    }

    /** @param  array<string, mixed>  $agency */
    protected function agencyUrl(array $agency): string
    {
        return $agency['slug'] !== '' ? '/byrakartan/'.rawurlencode($agency['slug']) : '/byrakartan';
    }

    /**
     * Mapbox-token till webbläsaren. Bara publika tokens (pk.…) är avsedda att synas i
     * en sida; en hemlig token (sk.…) som hamnat i MAPBOX_TOKEN skickas aldrig dit.
     */
    protected function publicMapToken(): string
    {
        $token = (string) config('services.mapbox.token');

        return preg_match('/^pk\.[A-Za-z0-9._-]+$/', $token) === 1 ? $token : '';
    }

    /** Stilhandtag för kartan (config services.mapbox.{$key}); ett ogiltigt värde ger standardstilen för sidan. */
    protected function mapStyle(string $key, string $default): string
    {
        $style = (string) config('services.mapbox.'.$key);

        return preg_match('~^[a-z0-9_-]+/[a-z0-9._-]+$~i', $style) === 1 ? $style : $default;
    }

    /** Fritext i sökfältet: trimmad, utan taggar och högst 80 tecken. */
    protected function query(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim(strip_tags($value)), 0, 80) : '';
    }

    /**
     * "lat,lng" från webbläsarens position. Avrundas till två decimaler (ca 1 km),
     * så att ingen exakt position behöver följa med i adressen.
     *
     * @return array{0: float, 1: float}|null
     */
    protected function coordinates(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)$/', trim($value), $m)) {
            return null;
        }

        [$lat, $lng] = [(float) $m[1], (float) $m[2]];

        return abs($lat) <= 90 && abs($lng) <= 180 ? [round($lat, 2), round($lng, 2)] : null;
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array<string, mixed>  $agency
     */
    protected function distanceKm(array $from, array $agency): ?float
    {
        if (($agency['lat'] ?? null) === null || ($agency['lng'] ?? null) === null) {
            return null;
        }

        [$lat1, $lng1] = [deg2rad($from[0]), deg2rad($from[1])];
        [$lat2, $lng2] = [deg2rad($agency['lat']), deg2rad($agency['lng'])];

        $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a));
    }

    protected function distanceLabel(float $km): string
    {
        return ($km < 10 ? number_format($km, 1, ',', '') : (string) round($km)).' km';
    }

    /**
     * "relevans" behåller Byråkartans egen ordning (listan kommer redan sorterad så).
     * Byråer utan position hamnar sist när man sorterar på avstånd.
     *
     * @param  Collection<int, array<string, mixed>>  $results
     * @return Collection<int, array<string, mixed>>
     */
    protected function sorted($results, string $sort)
    {
        return match ($sort) {
            'narmast' => $results->sortBy(fn (array $a) => $a['distance'] ?? PHP_INT_MAX)->values(),
            'betyg' => $results->sortByDesc(fn (array $a) => [$a['rating'] ?? 0, $a['rating_count']])->values(),
            'namn' => $results->sortBy(fn (array $a) => mb_strtolower($a['name']), SORT_LOCALE_STRING)->values(),
            default => $results,
        };
    }
}
