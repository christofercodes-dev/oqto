<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Gör om en Vimeo-länk till början av spelarens URL: "ID?" eller, för dolda
 * videor (som har en integritetskod i länken), "ID?h=KOD&". Mallen lägger
 * själv till resten av parametrarna efter modifierns resultat:
 *
 *   src="https://player.vimeo.com/video/{{ embed_code | vimeo_embed }}title=0&byline=0"
 *
 * Förstår både https://vimeo.com/123456789, https://vimeo.com/123456789/abcdef1234
 * (med eller utan ?share=…) och player.vimeo.com/video/123456789?h=abcdef1234.
 */
class VimeoEmbed extends Modifier
{
    public function index($value, $params, $context)
    {
        $value = trim((string) $value);

        // Ett rent id utan länk, t.ex. "123456789".
        if (preg_match('/^\d+$/', $value)) {
            return $value.'?';
        }

        $path = (string) parse_url($value, PHP_URL_PATH);
        $query = (string) parse_url($value, PHP_URL_QUERY);
        parse_str($query, $params);

        if (! preg_match('#(?:^|/)(\d+)(?:/([A-Za-z0-9]+))?/?$#', $path, $m)) {
            // Okänd form: behåll det gamla beteendet (sista delen av sökvägen).
            $last = basename($path);

            return rawurlencode($last).'?';
        }

        $hash = $m[2] ?? ($params['h'] ?? null);

        return $m[1].'?'.($hash !== null && $hash !== '' ? 'h='.rawurlencode($hash).'&' : '');
    }
}
