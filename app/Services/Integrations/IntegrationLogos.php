<?php

namespace App\Services\Integrations;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;
use Throwable;

/**
 * Vilka integrationer som saknar logotyp.
 *
 * En logotyp kommer antingen från källan (fältet "avatar", som importen fyller
 * i från avatarKey i exportfilen) eller är uppladdad för hand (fältet
 * "custom_avatar", som importen aldrig rör). Saknar källan en logotyp får
 * integrationen ingen alls förrän någon laddar upp en egen.
 */
class IntegrationLogos
{
    public const COLLECTION = 'integrations';

    public static function has(EntryContract $entry): bool
    {
        return self::filled($entry->get('avatar')) || self::filled($entry->get('custom_avatar'));
    }

    /**
     * Webbadressen till integrationens logotyp, annars null (då visas bokstaven).
     *
     * Adressen byggs direkt av sökvägen som sparats på posten och frågar aldrig
     * Statamics bildlista eller bildmetadata. Statamic cachar bildlistan, och är
     * den gammal (en logotyp som kom med i nattens import) räknas bilden som
     * saknad och {{ avatar }} blir tomt - sidan visade då en bokstav tills nästa
     * deploy. En signerad adress behöver ingen lista, bara sökvägen.
     *
     * Logotypen från källan (avatar, privat bucket) signeras tillfälligt;
     * en uppladdad (custom_avatar) ligger i den publika asset-containern.
     */
    public static function url(EntryContract $entry, int $minutes = 30): ?string
    {
        $path = self::first($entry->get('avatar'));
        $container = 'integrations';

        if ($path === null) {
            $path = self::first($entry->get('custom_avatar'));
            $container = 'assets';
        }

        if ($path === null) {
            return null;
        }

        try {
            $assetContainer = AssetContainer::find($container);

            if (! $assetContainer) {
                return null;
            }

            $disk = Storage::disk($assetContainer->diskHandle());

            if ($disk->providesTemporaryUrls()) {
                return $disk->temporaryUrl($path, now()->addMinutes($minutes));
            }

            $encoded = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));

            return rtrim((string) $assetContainer->url(), '/').'/'.$encoded;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Integrationer utan logotyp, i alfabetisk ordning.
     *
     * @return Collection<int, EntryContract>
     */
    public static function missing(bool $onlyPublished = false): Collection
    {
        return Entry::whereCollection(self::COLLECTION)
            ->filter(fn (EntryContract $entry) => ! self::has($entry) && (! $onlyPublished || $entry->published()))
            ->sortBy(fn (EntryContract $entry) => mb_strtolower((string) $entry->get('title')))
            ->values();
    }

    protected static function first(mixed $value): ?string
    {
        $path = collect((array) $value)->first(fn ($path) => is_string($path) && trim($path) !== '');

        return $path === null ? null : trim($path);
    }

    /**
     * Fältet kan vara en lista, en sträng eller tomt (äldre poster, rensade fält).
     */
    protected static function filled(mixed $value): bool
    {
        return collect((array) $value)->filter(fn ($path) => is_string($path) && trim($path) !== '')->isNotEmpty();
    }
}
