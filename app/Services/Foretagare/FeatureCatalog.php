<?php

namespace App\Services\Foretagare;

use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

/**
 * Funktionerna under "För företagare" är den enda källan till innehållet.
 * Byråerna (samlingen "agencies") väljer bara vilka av dem som visas och i
 * vilken ordning, och ger dem sin egen färg och logga - texterna, bilderna
 * och skärmbilderna hämtas alltid från samma funktionsposter.
 */
class FeatureCatalog
{
    public const FEATURES = 'foretagare_funktioner';

    public const AGENCIES = 'agencies';

    public const DEFAULT_BRAND = '#ff6319';

    public const DARK_INK = '#1a1715';

    /**
     * Adressen byråerna bäddar in från. Fast värde (inte APP_URL), eftersom koden
     * skickas vidare till byråerna och ska peka på den riktiga sajten.
     */
    public const EMBED_ORIGIN = 'https://oqto.se';

    /**
     * Typsnitten en byrå kan välja för rubriker och brödtext (Google Fonts).
     * Nyckeln sparas på byrån, resten är fasta värden som hamnar i CSS och
     * i adressen till typsnittets stilmall, så att inget annat än dessa går att välja.
     *
     * @var array<string, array{name: string, family: string, google: string, serif: bool}>
     */
    public const FONTS = [
        'inter' => ['name' => 'Inter', 'family' => "'Inter', sans-serif", 'google' => 'Inter:wght@400;500;600;700', 'serif' => false],
        'manrope' => ['name' => 'Manrope', 'family' => "'Manrope', sans-serif", 'google' => 'Manrope:wght@400;500;600;700', 'serif' => false],
        'roboto' => ['name' => 'Roboto', 'family' => "'Roboto', sans-serif", 'google' => 'Roboto:wght@400;500;700', 'serif' => false],
        'noto-serif' => ['name' => 'Noto Serif', 'family' => "'Noto Serif', serif", 'google' => 'Noto+Serif:wght@400;500;600;700', 'serif' => true],
        'source-serif-4' => ['name' => 'Source Serif 4', 'family' => "'Source Serif 4', serif", 'google' => 'Source+Serif+4:wght@400;500;600;700', 'serif' => true],
    ];

    /**
     * Publicerad byrå med givet slug, annars null.
     */
    public function agency(string $slug): ?EntryContract
    {
        $agency = Entry::query()
            ->where('collection', self::AGENCIES)
            ->where('slug', $slug)
            ->first();

        return $agency && $agency->published() ? $agency : null;
    }

    /**
     * De funktioner som visas: byråns urval (i vald ordning) eller, utan byrå
     * eller utan något urval, alla publicerade funktioner i sorteringsordning.
     *
     * @return Collection<int, EntryContract>
     */
    public function features(?EntryContract $agency = null): Collection
    {
        $selected = $agency ? array_values(array_filter((array) $agency->get('features'))) : [];

        if ($selected !== []) {
            return collect($selected)
                ->map(fn ($id) => Entry::find($id))
                ->filter(fn ($entry) => $entry
                    && $entry->collectionHandle() === self::FEATURES
                    && $entry->published())
                ->values();
        }

        return Entry::query()
            ->where('collection', self::FEATURES)
            ->where('published', true)
            ->get()
            ->sortBy([
                fn ($a, $b) => $this->orderOf($a) <=> $this->orderOf($b),
                fn ($a, $b) => strnatcasecmp((string) $a->get('title'), (string) $b->get('title')),
            ])
            ->values();
    }

    public function feature(string $slug, ?EntryContract $agency = null): ?EntryContract
    {
        return $this->features($agency)->first(fn (EntryContract $entry) => $entry->slug() === $slug);
    }

    /**
     * Listdata för ett kort: titel, kort beskrivning, bild och adress.
     *
     * @return array<string, mixed>
     */
    public function card(EntryContract $feature, ?EntryContract $agency = null): array
    {
        return [
            'title' => (string) $feature->get('title'),
            'slug' => $feature->slug(),
            'short_description' => $feature->get('short_description'),
            'image_url' => $this->assetUrl($feature, 'image'),
            'hero_visual' => (string) ($feature->get('hero_visual') ?? ''),
            'href' => $this->featureUrl($feature, $agency),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cards(?EntryContract $agency = null): array
    {
        return $this->features($agency)
            ->map(fn (EntryContract $feature) => $this->card($feature, $agency))
            ->all();
    }

    public function featureUrl(EntryContract $feature, ?EntryContract $agency = null): string
    {
        return $agency
            ? $this->agencyUrl($agency).'/'.$feature->slug()
            : '/for-foretagare/'.$feature->slug();
    }

    public function agencyUrl(EntryContract $agency): string
    {
        return '/byra/'.$agency->slug();
    }

    /**
     * Allt en mall behöver veta om en byrå: namn, logga, färger, kontakt.
     *
     * @return array<string, mixed>
     */
    public function agencyData(EntryContract $agency): array
    {
        $contactUrl = trim((string) $agency->get('contact_url'));
        $contactEmail = trim((string) $agency->get('contact_email'));

        return [
            'name' => (string) $agency->get('title'),
            'slug' => $agency->slug(),
            'base' => $this->agencyUrl($agency),
            'logo_url' => $this->assetUrl($agency, 'logo'),
            // Topplisten visas om inget annat valts (äldre byråer saknar fältet).
            'show_topbar' => $agency->get('show_topbar') !== false,
            // Inbäddning på andra sajter är av om inget annat valts.
            'allow_embed' => $agency->get('allow_embed') === true,
            'embed_sources' => $this->embedSources($agency),
            'brand_style' => $this->brandStyle($agency->get('brand_color'), $agency->get('heading_font'), $agency->get('body_font')),
            'heading_font' => $this->fontKey($agency->get('heading_font')),
            'body_font' => $this->fontKey($agency->get('body_font')),
            'font_stylesheet' => $this->fontStylesheet($agency->get('heading_font'), $agency->get('body_font')),
            'cta_label' => trim((string) $agency->get('cta_label')) ?: 'Kontakta oss',
            'cta_url' => $contactUrl !== '' ? $contactUrl : ($contactEmail !== '' ? 'mailto:'.$contactEmail : null),
            'intro_title' => trim((string) $agency->get('intro_title')) ?: 'Funktioner för dig som är kund hos '.$agency->get('title'),
            'intro_text' => $agency->get('intro_text'),
        ];
    }

    /**
     * Färdig inbäddningskod för en byrå (iframe + skript som anpassar höjden),
     * redo att skickas till byrån. Visas i byråns formulär i kontrollpanelen.
     */
    public function embedCode(?string $slug): string
    {
        $slug = trim((string) $slug) !== '' ? rawurlencode(trim((string) $slug)) : 'byraens-adress';
        $origin = self::EMBED_ORIGIN;

        return <<<HTML
            <!-- Oqto: lägg in den här koden där sidan ska visas -->
            <iframe
              id="oqto-byra"
              src="{$origin}/byra/{$slug}"
              title="Funktioner för våra kunder"
              allow="fullscreen"
              style="width: 100%; height: 800px; border: 0; display: block;"
            ></iframe>

            <!-- Anpassar ramens höjd efter innehållet, så att den inte får en egen scrollruta -->
            <script>
              (function () {
                var frame = document.getElementById('oqto-byra');

                window.addEventListener('message', function (e) {
                  // Lyssna bara på meddelanden från Oqto och från just den här ramen.
                  if (e.origin !== '{$origin}' || e.source !== frame.contentWindow) return;

                  if (e.data && e.data.type === 'oqto:embed-height') {
                    frame.style.height = e.data.height + 'px';
                  }
                });
              })();
            </script>
            HTML;
    }

    /**
     * Adresserna (CSP-källor) som får visa byråns sidor i en ram, t.ex.
     * ["https://digim.se", "https://www.digim.se"]. Tom lista = ingen begränsning.
     *
     * Värdena skrivs av redaktörer och hamnar i en säkerhetsrubrik, så allt som
     * inte är en ren adress (domän, "*.domän", gärna med http(s):// och port,
     * annars rensas bort) hoppas över - inget annat än adresser kan nå rubriken.
     * "digim.se" släpper in både digim.se och www.digim.se; "*.digim.se" även
     * underdomäner. localhost kräver http, alla andra https.
     *
     * @return array<int, string>
     */
    public function embedSources(EntryContract $agency): array
    {
        $sources = [];

        foreach ((array) $agency->get('embed_domains') as $value) {
            if (! is_string($value)) {
                continue;
            }

            $host = strtolower(trim($value));
            $host = preg_replace('#^https?://#', '', $host);
            $host = explode('/', $host, 2)[0];

            if (! preg_match('/^(\*\.)?(www\.)?((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}|localhost)(:\d{1,5})?$/', $host, $m)) {
                continue;
            }

            $wildcard = $m[1] !== '';
            $base = $m[3].($m[4] ?? '');
            $scheme = $m[3] === 'localhost' ? 'http' : 'https';

            if ($wildcard) {
                $sources[] = "{$scheme}://*.{$base}";
                $sources[] = "{$scheme}://{$base}";

                continue;
            }

            $sources[] = "{$scheme}://{$base}";

            if ($m[3] !== 'localhost') {
                $sources[] = "{$scheme}://www.{$base}";
            }
        }

        return array_slice(array_values(array_unique($sources)), 0, 40);
    }

    /**
     * CSS-variablerna för en färg: huvudfärgen och en textfärg som går att
     * läsa ovanpå den. Ogiltiga värden (allt som inte är en hexfärg) ersätts
     * med Oqtos orange, så att inget annat än en färg kan hamna i style-attributet.
     */
    public function brandStyle(mixed $color, mixed $headingFont = null, mixed $bodyFont = null): string
    {
        $hex = $this->normalizeHex($color) ?? self::DEFAULT_BRAND;

        $style = sprintf('--brand: %s; --brand-ink: %s;', $hex, $this->inkFor($hex));

        if ($heading = $this->fontKey($headingFont)) {
            // Serifer behöver mindre tät bokstavsföljd i rubrikerna än Oqtos egna typsnitt.
            $style .= sprintf(
                ' --brand-font-heading: %s; --brand-heading-tracking: %s;',
                self::FONTS[$heading]['family'],
                self::FONTS[$heading]['serif'] ? '-0.015em' : '-0.03em'
            );
        }

        if ($body = $this->fontKey($bodyFont)) {
            $style .= sprintf(' --brand-font-body: %s;', self::FONTS[$body]['family']);
        }

        return $style;
    }

    /**
     * Nyckeln för ett giltigt typsnitt, annars null (Oqtos eget typsnitt).
     */
    public function fontKey(mixed $font): ?string
    {
        return is_string($font) && isset(self::FONTS[$font]) ? $font : null;
    }

    /**
     * Adressen till Google Fonts-stilmallen för de valda typsnitten, annars null.
     */
    public function fontStylesheet(mixed ...$fonts): ?string
    {
        $families = collect($fonts)
            ->map(fn ($font) => $this->fontKey($font))
            ->filter()
            ->unique()
            ->map(fn (string $key) => 'family='.self::FONTS[$key]['google'])
            ->values();

        return $families->isEmpty() ? null : 'https://fonts.googleapis.com/css2?'.$families->implode('&').'&display=swap';
    }

    public function normalizeHex(mixed $color): ?string
    {
        if (! is_string($color) || ! preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($color), $m)) {
            return null;
        }

        $hex = strtolower($m[1]);

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }

    /**
     * Vit eller mörk text, beroende på vad som ger bäst läsbarhet mot färgen
     * (relativ luminans enligt WCAG).
     */
    public function inkFor(string $hex): string
    {
        $channels = array_map(function (string $part) {
            $c = hexdec($part) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        // Vit text om kontrasten mot vitt är minst lika bra som mot mörkt.
        return $luminance > 0.179 ? self::DARK_INK : '#ffffff';
    }

    protected function orderOf(EntryContract $entry): int|float
    {
        $order = $entry->get('order');

        return is_numeric($order) ? (float) $order : INF;
    }

    protected function assetUrl(EntryContract $entry, string $field): ?string
    {
        $asset = $entry->augmentedValue($field)?->value();

        return $asset?->url();
    }
}
