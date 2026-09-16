<?php

namespace App\Modifiers;

use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Modifiers\Modifier;

/**
 * Genererar en tillfällig, förhandssignerad S3-URL för en asset i en privat
 * container (t.ex. "integrations" - se config/filesystems.php, disken har
 * medvetet ingen "url" eftersom bucketen är privat). Statamics vanliga
 * {{ avatar:url }} returnerar null för sådana containrar.
 *
 * OBS: måste anropas med pipe-syntax ({{ avatar | signed_url }}), inte
 * kolon-genvägen ({{ avatar:signed_url }}) - den senare försöker slå upp
 * "signed_url" som ett augmenterat asset-fält (som "url"/"width") istället
 * för att köra en modifier, och ger då alltid null.
 *
 * Användning: {{ avatar | signed_url }} eller {{ avatar | signed_url:60 }}
 * (parametern är giltighetstid i minuter, default 30).
 *
 * I en loop över ett flerval-fält (t.ex. "media") är den nuvarande assetens
 * fält utspridda i scopet snarare än assetet själv - piepa då dess "id"
 * (container::sökväg) istället: {{ media }}{{ id | signed_url }}{{ /media }}
 */
class SignedUrl extends Modifier
{
    public function index($value, $params, $context)
    {
        $minutes = (int) ($params[0] ?? 30);

        $asset = $this->resolveAsset($value);

        if ($asset) {
            return $this->signedUrlFor($asset, $minutes);
        }

        if (is_iterable($value)) {
            return collect($value)
                ->map(fn ($item) => ($item = $this->resolveAsset($item)) ? $this->signedUrlFor($item, $minutes) : null)
                ->filter()
                ->values()
                ->all();
        }

        return null;
    }

    protected function resolveAsset($value): ?Asset
    {
        if ($value instanceof Asset) {
            return $value;
        }

        if (is_string($value) && str_contains($value, '::')) {
            return AssetFacade::find($value);
        }

        return null;
    }

    protected function signedUrlFor(Asset $asset, int $minutes): ?string
    {
        $disk = Storage::disk($asset->container()->diskHandle());

        if (! $disk->providesTemporaryUrls()) {
            return $asset->url();
        }

        return $disk->temporaryUrl($asset->path(), now()->addMinutes($minutes));
    }
}
