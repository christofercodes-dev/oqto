<?php

namespace App\Tags;

use Statamic\Tags\Tags;

/**
 * Hjälptaggar för metataggarna i <head> (delningsbild, adress).
 */
class Seo extends Tags
{
    protected static $handle = 'seo';

    /** Delningsbilden som används på alla sidor utan egen bild (se resources/og/default.html). */
    public const DEFAULT_IMAGE = '/images/og/oqto-default.png';

    /**
     * {{ seo:origin }} - sajtens adress utan avslutande snedstreck, t.ex. https://oqto.se.
     * Delningsbilder och og:url måste vara absoluta. APP_URL gäller om den är satt till
     * något annat än localhost; annars används adressen som besökaren kom in på.
     */
    public function origin(): string
    {
        $configured = rtrim((string) config('app.url'), '/');

        if ($configured !== '' && ! str_contains($configured, 'localhost') && ! str_contains($configured, '127.0.0.1')) {
            return $configured;
        }

        return request()->getSchemeAndHttpHost();
    }

    /** {{ seo:url }} - sidans adress utan query-sträng. */
    public function url(): string
    {
        return $this->origin().'/'.ltrim(request()->path() === '/' ? '' : request()->path(), '/');
    }

    /** {{ seo:default_image }} - absolut adress till standardbilden. */
    public function defaultImage(): string
    {
        return $this->origin().self::DEFAULT_IMAGE;
    }
}
