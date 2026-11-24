<?php

namespace App\Services\Byrakartan;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Svenskt postnummer -> position, så att byråerna på /byrakartan kan sorteras efter
 * avstånd. Samma uppslag som Byråkartan själv gör (Mapbox Geocoding). Bara det
 * postnummer besökaren skrev skickas, aldrig något annat. Tyst no-op utan
 * MAPBOX_TOKEN eller vid fel: sökningen fungerar då som vanligt, utan avstånd.
 */
class PostcodeLocator
{
    public function enabled(): bool
    {
        return filled(config('services.mapbox.token'));
    }

    /** Ser texten ut som ett svenskt postnummer ("241 96" eller "24196")? */
    public function looksLikePostcode(string $input): bool
    {
        return preg_match('/^\s*\d{3}\s?\d{2}\s*$/', $input) === 1;
    }

    /**
     * @return array{point: array{0: float, 1: float}, label: string}|null
     */
    public function lookup(string $input): ?array
    {
        if (! $this->enabled() || ! preg_match('/^\s*(\d{3})\s?(\d{2})\s*$/', $input, $m)) {
            return null;
        }

        $code = $m[1].$m[2];

        // Ett postnummer ger alltid samma plats, så svaret cachas (null cachas inte).
        return Cache::remember("byrakartan.postcode.$code", now()->addDays(30), function () use ($code, $m) {
            try {
                $feature = Http::connectTimeout(3)->timeout(4)->get('https://api.mapbox.com/search/geocode/v6/forward', [
                    'q' => $code,
                    'types' => 'postcode',
                    'country' => 'se',
                    'limit' => 1,
                    'language' => 'sv',
                    'access_token' => config('services.mapbox.token'),
                ])->json('features.0');
            } catch (Throwable) {
                return null;
            }

            $coordinates = $feature['geometry']['coordinates'] ?? null;

            if (! is_array($coordinates) || count($coordinates) !== 2 || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
                return null;
            }

            $place = $feature['properties']['context']['place']['name'] ?? '';

            return [
                'point' => [round((float) $coordinates[1], 5), round((float) $coordinates[0], 5)], // Mapbox: [lng, lat]
                'label' => trim($m[1].' '.$m[2].' '.(is_string($place) ? strip_tags($place) : '')),
            ];
        });
    }
}
