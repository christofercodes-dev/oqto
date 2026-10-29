<?php

namespace App\Tags;

use App\Services\Foretagare\FeatureCatalog;
use Statamic\Tags\Tags;

/**
 * Hjälptaggar för sidorna under "För företagare" och byråernas sidor.
 */
class Foretagare extends Tags
{
    protected static $handle = 'foretagare';

    /**
     * {{ foretagare:features as="features" }} ... {{ /foretagare:features }}
     * Funktionerna som ska visas, med title, slug, short_description,
     * image_url och href. Med agency="slug" (eller en byrå i mallens
     * kontext) gäller byråns urval och adresserna går under /byra/{slug}.
     * exclude="slug" utelämnar en funktion (t.ex. den som visas just nu).
     */
    public function features()
    {
        $catalog = app(FeatureCatalog::class);

        $slug = $this->params->get('agency') ?? ($this->context->get('agency')['slug'] ?? null);
        $agency = $slug ? $catalog->agency((string) $slug) : null;

        $exclude = $this->params->get('exclude');

        $cards = collect($catalog->cards($agency))
            ->reject(fn (array $card) => $exclude && $card['slug'] === $exclude)
            ->values()
            ->all();

        return $this->aliasedResult($cards);
    }
}
