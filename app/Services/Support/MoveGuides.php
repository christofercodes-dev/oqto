<?php

namespace App\Services\Support;

/**
 * Gör om guiderna i kategorin "Flytta till Oqto" till ett val i tre steg:
 * först ekonomisystemet, sedan hur man vill flytta (via integration eller
 * manuellt med filer) och sist guiderna för just den kombinationen.
 *
 * Artiklarna kommer från Intercom och har inga egna taggar, så de sorteras på
 * titeln: systemet är ordet efter "från" ("Exportera filer från Fortnox"),
 * sättet avgörs av ord som "integration", "Importguiden" och "Exportera",
 * och felsökning och övriga guider hör till båda sätten.
 */
class MoveGuides
{
    public const OTHER_SYSTEM = 'Övriga guider';

    /**
     * @param  array{sections?: array<int, array{articles?: array<int, array<string, mixed>>}>}  $category
     * @return array{systems: array<int, array<string, mixed>>, initial: string}|null
     */
    public static function build(array $category): ?array
    {
        $articles = collect($category['sections'] ?? [])
            ->flatMap(fn (array $section) => $section['articles'] ?? [])
            ->values();

        if ($articles->isEmpty()) {
            return null;
        }

        $groups = [];

        foreach ($articles as $article) {
            $name = self::systemOf((string) ($article['title'] ?? '')) ?? self::OTHER_SYSTEM;

            $groups[$name][self::methodOf((string) ($article['title'] ?? ''))][] = $article;
        }

        // Utan något identifierat system finns inget att välja mellan.
        if (array_keys($groups) === [self::OTHER_SYSTEM]) {
            return null;
        }

        ksort($groups);

        // "Övriga guider" ska alltid ligga sist.
        if (isset($groups[self::OTHER_SYSTEM])) {
            $other = $groups[self::OTHER_SYSTEM];
            unset($groups[self::OTHER_SYSTEM]);
            $groups[self::OTHER_SYSTEM] = $other;
        }

        $systems = [];

        foreach ($groups as $name => $buckets) {
            $systems[] = self::system((string) $name, $buckets);
        }

        $initial = count($systems) === 1 ? $systems[0]['go'] : 'system';

        return ['systems' => $systems, 'initial' => $initial];
    }

    public static function systemOf(string $title): ?string
    {
        if (preg_match('/\bfrån\s+([\p{L}][\p{L}\d]*)/iu', $title, $match) !== 1) {
            return null;
        }

        return mb_strtoupper(mb_substr($match[1], 0, 1)).mb_substr($match[1], 1);
    }

    /**
     * @return 'integration'|'manual'|'help'
     */
    public static function methodOf(string $title): string
    {
        if (preg_match('/felsök/iu', $title) === 1) {
            return 'help';
        }

        if (preg_match('/integration/iu', $title) === 1) {
            return 'integration';
        }

        if (preg_match('/importguide|exportera|filer/iu', $title) === 1) {
            return 'manual';
        }

        return 'help';
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $buckets
     * @return array<string, mixed>
     */
    private static function system(string $name, array $buckets): array
    {
        $key = self::slug($name);

        // Exportera först, sedan själva importen.
        $manual = collect($buckets['manual'] ?? [])
            ->sortBy(fn (array $a) => preg_match('/exportera/iu', (string) $a['title']) === 1 ? 0 : 1)
            ->values()
            ->all();

        $help = $buckets['help'] ?? [];

        $methods = [];

        if (! empty($buckets['integration'])) {
            $methods[] = [
                'key' => 'integration',
                'title' => 'Via integration',
                'text' => "Oqto hämtar bolaget automatiskt från {$name}, utan att du exporterar några filer. Finns idag för redovisningsbyråer.",
                'kind' => 'plug',
                'articles' => $buckets['integration'],
            ];
        }

        if ($manual !== []) {
            $methods[] = [
                'key' => 'manual',
                'title' => 'Manuellt med filer',
                'text' => "Du exporterar filer från {$name} och laddar upp dem i Importguiden i Oqto. Passar när företaget redan finns i Oqto.",
                'kind' => 'upload',
                'articles' => $manual,
            ];
        }

        // Bara felsökning eller övriga guider: en enda lista utan val.
        if ($methods === []) {
            $methods[] = [
                'key' => 'guides',
                'title' => 'Guider',
                'text' => '',
                'kind' => 'doc',
                'articles' => $help,
            ];

            $help = [];
        }

        $single = count($methods) === 1;
        $count = 0;

        foreach ($methods as $i => $method) {
            $sections = [['title' => null, 'articles' => $method['articles']]];

            if ($help !== []) {
                $sections[] = ['title' => 'Om något går fel', 'articles' => $help];
            }

            $methods[$i] = $method + [
                'scope' => self::scopeFor($method),
                'id' => "{$key}-{$method['key']}",
                'guides' => count($method['articles']) + count($help),
                'sections' => $sections,
                // Finns bara ett sätt hoppar man över steget och går tillbaka till systemen.
                'back' => $single ? 'system' : $key,
            ];

            $count += count($method['articles']);
        }

        return [
            'key' => $key,
            'name' => $name,
            'letter' => mb_strtoupper(mb_substr($name, 0, 1)),
            // OBS: inte "count" - det är räknaren i Antlers loopar.
            'guides' => $count + count($help),
            'single' => $single,
            'go' => $single ? $methods[0]['id'] : $key,
            'methods' => $methods,
        ];
    }

    /**
     * Vad som följer med och inte, hämtat ordagrant ur guiderna för sättet
     * (integrationsguiden eller Importguiden). Inget skrivs om - bara
     * rubrikerna, listpunkterna och styckena som redan står i hjälpfilerna
     * plockas ut.
     *
     * @param  array<string, mixed>  $method
     * @return array<string, mixed>|null
     */
    private static function scopeFor(array $method): ?array
    {
        foreach ($method['articles'] as $article) {
            if (! in_array($method['key'], ['integration', 'manual'], true)) {
                break;
            }

            // Exportera-guiden hör till Importguiden men beskriver inte vad som följer med.
            if ($method['key'] === 'manual' && preg_match('/importguide/iu', (string) ($article['title'] ?? '')) !== 1) {
                continue;
            }

            $scope = self::scopeOf((string) ($article['body'] ?? ''));

            if ($scope !== null) {
                return $scope + [
                    'url' => (string) ($article['url'] ?? ''),
                    'article' => (string) ($article['title'] ?? ''),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{title: ?string, follows_title: ?string, follows: array<int, string>, skips_title: ?string, skips: array<int, string>, notes: array<int, string>}|null
     */
    public static function scopeOf(string $html): ?array
    {
        // Rutor med tips ("👉 ...") är inte en del av listan.
        $html = (string) preg_replace('~<div class="intercom-interblocks-callout".*?</div>~su', '', $html);

        $with = self::section($html, '/^\s*Det här följer med\s*$/iu');
        $without = self::section($html, '/^\s*Det här följer inte med\s*$/iu');

        if ($with !== null) {
            $scope = [
                'title' => null,
                'follows_title' => $with['title'],
                'follows' => $with['items'],
                'skips_title' => $without['title'] ?? null,
                'skips' => $without['items'] ?? [],
                'notes' => array_merge($with['notes'], $without['notes'] ?? []),
            ];
        } elseif (($single = self::section($html, '/Vad följer med/iu')) !== null) {
            // Importguiden: en rubrik med lista och/eller stycke i stället för två listor.
            $scope = [
                'title' => $single['title'],
                'follows_title' => null,
                'follows' => $single['items'],
                'skips_title' => null,
                'skips' => [],
                'notes' => $single['notes'],
            ];
        } else {
            return null;
        }

        return $scope['follows'] !== [] || $scope['skips'] !== [] || $scope['notes'] !== [] ? $scope : null;
    }

    /**
     * Texten under den första rubriken som matchar, fram till nästa rubrik.
     *
     * @return array{title: string, items: array<int, string>, notes: array<int, string>}|null
     */
    private static function section(string $html, string $headingPattern): ?array
    {
        preg_match_all('~<h([1-6])[^>]*>(.*?)</h\1>~su', $html, $headings, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($headings as $i => $heading) {
            $title = trim(html_entity_decode(strip_tags($heading[2][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (preg_match($headingPattern, $title) !== 1) {
                continue;
            }

            $from = $heading[0][1] + strlen($heading[0][0]);
            $to = isset($headings[$i + 1]) ? $headings[$i + 1][0][1] : strlen($html);
            $body = substr($html, $from, $to - $from);

            preg_match_all('~<li[^>]*>(.*?)</li>~su', $body, $items);

            $withoutLists = (string) preg_replace('~<(ul|ol)[^>]*>.*?</\1>~su', '', $body);

            preg_match_all('~<p[^>]*>(.*?)</p>~su', $withoutLists, $paragraphs);

            return [
                'title' => $title,
                'items' => array_values(array_filter(array_map([self::class, 'inline'], $items[1]))),
                'notes' => array_values(array_filter(array_map([self::class, 'inline'], $paragraphs[1]))),
            ];
        }

        return null;
    }

    /**
     * Texten med bara fetstil kvar (så som den står i guiden).
     */
    private static function inline(string $html): string
    {
        $text = (string) preg_replace('~<br\s*/?>~i', ' ', $html);
        $text = strip_tags($text, '<b><strong>');
        $text = (string) preg_replace('~<(/?)b(?:\s[^>]*)?>~i', '<$1strong>', $text);
        $text = (string) preg_replace('~<strong\s[^>]*>~i', '<strong>', $text);
        $text = (string) preg_replace('~<strong>\s*</strong>~i', '', $text);

        return trim((string) preg_replace('~\s+~u', ' ', $text));
    }

    private static function slug(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name), '-'));

        return $slug !== '' ? $slug : 'ovrigt';
    }
}
