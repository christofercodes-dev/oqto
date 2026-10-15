<?php

namespace App\Services\Intercom;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Rhukster\DomSanitizer\DOMSanitizer;
use Throwable;

/**
 * Hjälpcentret från Intercom, redo att visas på /support.
 *
 * Hela datamängden (samlingar + publicerade artiklar med text) hämtas i ett
 * svep och cachas. Sidorna läser bara ur cachen; den fylls på av
 * schemaläggaren, av webhooken (som anropar refresh()) och - första gången
 * - av den första besökaren. Misslyckas en hämtning används den senast
 * lyckade kopian ("stale"), så sidorna går inte ner om Intercom gör det.
 *
 * Resultatet har formen:
 *   categories: [{id, slug, title, description, order, article_count, sections: [{title, articles: [...]}]}]
 *   articles:   [{id, slug, title, description, body, category_slug, category_title, section_title, url, updated_at}]
 */
class HelpCenter
{
    protected const CACHE_KEY = 'intercom.help_center';

    protected const STALE_KEY = 'intercom.help_center.stale';

    protected const FAILED_KEY = 'intercom.help_center.failed';

    public function __construct(protected IntercomClient $client) {}

    /**
     * @return array{categories: array<int, array<string, mixed>>, articles: array<int, array<string, mixed>>, synced_at: ?string}
     */
    public function data(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        // Efter ett misslyckat försök väntar vi en minut innan nästa, så att
        // sidan inte hamrar på Intercom (och blir seg) medan API:t ligger nere.
        if (Cache::has(self::FAILED_KEY)) {
            return Cache::get(self::STALE_KEY) ?? $this->empty();
        }

        try {
            return $this->refresh();
        } catch (Throwable $e) {
            Cache::put(self::FAILED_KEY, true, 60);

            Log::warning('Intercom-hämtning misslyckades, använder senaste kopian: '.$e->getMessage());

            return Cache::get(self::STALE_KEY) ?? $this->empty();
        }
    }

    /**
     * Hämtar om allt från Intercom och ersätter cachen. Kastar vidare vid fel
     * (anroparen - kommandot eller webhooken - loggar), men lämnar då den
     * gamla cachen orörd.
     *
     * @return array{categories: array<int, array<string, mixed>>, articles: array<int, array<string, mixed>>, synced_at: ?string}
     */
    public function refresh(): array
    {
        $data = $this->build();

        Cache::put(self::CACHE_KEY, $data, (int) config('services.intercom.cache_ttl', 3600));
        Cache::forever(self::STALE_KEY, $data);
        Cache::forget(self::FAILED_KEY);

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function category(string $slug): ?array
    {
        return collect($this->data()['categories'])->firstWhere('slug', $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function article(string $categorySlug, string $articleSlug): ?array
    {
        return collect($this->data()['articles'])
            ->first(fn (array $article) => $article['category_slug'] === $categorySlug && $article['slug'] === $articleSlug);
    }

    /**
     * @return array{categories: array<int, array<string, mixed>>, articles: array<int, array<string, mixed>>, synced_at: ?string}
     */
    protected function build(): array
    {
        $collections = collect($this->client->collections())->keyBy(fn (array $c) => (string) $c['id']);
        $articles = $this->client->articles();

        $locale = (string) config('services.intercom.locale', 'sv');

        $categories = [];
        $items = [];
        $usedSlugs = [];

        foreach ($articles as $article) {
            if (($article['state'] ?? 'published') !== 'published') {
                continue;
            }

            $content = $this->localized($article, $locale);

            $title = $this->text($content['title'] ?? '');

            if ($title === '') {
                continue;
            }

            $parentId = $this->parentOf($article);

            if (! $this->isIncluded($parentId, $collections)) {
                continue;
            }

            [$topId, $sectionId] = $this->placeInTree($parentId, $collections);

            $category = $this->categoryFor($categories, $topId, $collections, $locale);

            $slug = $this->uniqueSlug($title, (string) $article['id'], $usedSlugs[$category['slug']] ?? []);
            $usedSlugs[$category['slug']][] = $slug;

            $sectionTitle = $sectionId && $sectionId !== $topId
                ? $this->text($this->localized($collections[$sectionId], $locale)['name'] ?? '')
                : null;

            $items[] = [
                'id' => (string) $article['id'],
                'content_id' => isset($article['content_id']) ? (string) $article['content_id'] : null,
                'slug' => $slug,
                'title' => $title,
                'description' => $this->plain($content['description'] ?? null),
                'kind' => $this->kindOf($title),
                'body' => $this->sanitize((string) ($content['body'] ?? '')),
                'category_slug' => $category['slug'],
                'category_title' => $category['title'],
                'section_title' => $sectionTitle ?: null,
                'url' => '/support/'.$category['slug'].'/'.$slug,
                'intercom_url' => $article['url'] ?? null,
                'updated_at' => isset($article['updated_at']) ? (int) $article['updated_at'] : null,
            ];
        }

        $items = $this->rewriteInternalLinks($items);

        // Intercoms API ger inte den manuella ordningen från hjälpcentret.
        // Skapandeordning (stigande id) är närmast - guider som hör ihop
        // skrivs oftast i ordning. Sektioner sorteras separat i finishCategories().
        usort($items, fn (array $a, array $b) => (int) $a['id'] <=> (int) $b['id']);

        return [
            'categories' => $this->finishCategories($categories, $items),
            'articles' => $items,
            'synced_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Artiklarna länkar till varandra med Intercoms egna adresser
     * (t.ex. https://intercom-help.eu/oqto/sv/articles/714412-titel). De
     * skrivs om till motsvarande sida på sajten, så länge artikeln finns här.
     * Länkar till andra sajter lämnas orörda.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function rewriteInternalLinks(array $items): array
    {
        $urls = collect($items)->pluck('url', 'id')->all();

        $hosts = collect($items)
            ->pluck('intercom_url')
            ->filter()
            ->map(fn ($url) => parse_url($url, PHP_URL_HOST))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $byContentId = collect($items)->whereNotNull('content_id')->pluck('url', 'content_id')->all();

        $hostPattern = $hosts === [] ? null : implode('|', array_map(fn ($host) => preg_quote($host, '~'), $hosts));

        return array_map(function (array $item) use ($urls, $byContentId, $hostPattern) {
            $body = $item['body'];

            if ($hostPattern) {
                $body = preg_replace_callback(
                    '~href="https?://(?:'.$hostPattern.')/[^"]*?/articles/(\d+)[^"]*"~',
                    fn (array $m) => isset($urls[$m[1]]) ? 'href="'.$urls[$m[1]].'"' : $m[0],
                    $body,
                );
            }

            // Ibland har redaktören klistrat in en länk till Intercoms admin
            // (app.eu.intercom.com/...?activeContentId=<content_id>) i stället
            // för artikelns publika adress - besökare kommer inte åt den.
            $body = preg_replace_callback(
                '~href="https?://app\.[a-z.]*intercom\.com/[^"]*?activeContentId=(\d+)[^"]*"~',
                fn (array $m) => isset($byContentId[$m[1]]) ? 'href="'.$byContentId[$m[1]].'"' : $m[0],
                $body,
            );

            $item['body'] = $body;

            return $item;
        }, $items);
    }

    /**
     * En artikel som ligger i flera samlingar har tomt parent_id och en lista
     * i parent_ids i stället - då används den första.
     *
     * @param  array<string, mixed>  $article
     */
    protected function parentOf(array $article): string
    {
        $parent = $article['parent_id'] ?? ($article['parent_ids'][0] ?? null);

        return $parent === null ? '' : (string) $parent;
    }

    /**
     * Med INTERCOM_COLLECTION_IDS satt visas bara artiklar som ligger i (eller
     * under) någon av de samlingarna. Artiklar utan samling räknas inte.
     */
    protected function isIncluded(string $parentId, $collections): bool
    {
        $allowed = array_map('strval', (array) config('services.intercom.collection_ids', []));

        if ($allowed === []) {
            return true;
        }

        $current = $parentId;
        $guard = 0;

        while ($current !== '' && $collections->has($current) && $guard++ < 10) {
            if (in_array($current, $allowed, true)) {
                return true;
            }

            $current = (string) ($collections[$current]['parent_id'] ?? '');
        }

        return false;
    }

    /**
     * Intercom har samlingar som kan ligga i varandra (samling > sektion).
     * Översta nivån blir en kategori på sidan; en eventuell nivå därunder
     * blir en rubrik inne i kategorin.
     *
     * @return array{0: string, 1: ?string} [id på översta samlingen, id på närmaste samlingen]
     */
    protected function placeInTree(string $parentId, $collections): array
    {
        if ($parentId === '' || ! $collections->has($parentId)) {
            return ['', null];
        }

        $current = $parentId;
        $guard = 0;

        while ($guard++ < 10) {
            $parent = (string) ($collections[$current]['parent_id'] ?? '');

            if ($parent === '' || ! $collections->has($parent)) {
                break;
            }

            $current = $parent;
        }

        return [$current, $parentId];
    }

    /**
     * @param  array<string, array<string, mixed>>  $categories
     * @return array<string, mixed>
     */
    protected function categoryFor(array &$categories, string $topId, $collections, string $locale): array
    {
        $key = $topId === '' ? 'ovrigt' : $topId;

        if (isset($categories[$key])) {
            return $categories[$key];
        }

        if ($topId === '') {
            $title = 'Övrigt';
            $description = null;
            $order = PHP_INT_MAX;
        } else {
            $content = $this->localized($collections[$topId], $locale);
            $title = $this->text($content['name'] ?? 'Okänd kategori');
            $description = $this->plain($content['description'] ?? null);
            $order = (int) ($collections[$topId]['order'] ?? 0);
        }

        // "status" är reserverat: /support/status leder vidare till /support.
        $used = array_merge(collect($categories)->pluck('slug')->all(), ['status']);

        return $categories[$key] = [
            'id' => $key,
            'slug' => $this->uniqueSlug($title, $key, $used),
            'title' => $title,
            'description' => $description,
            'order' => $order,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $categories
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function finishCategories(array $categories, array $items): array
    {
        $result = collect($categories)
            ->map(function (array $category) use ($items) {
                $inCategory = collect($items)->where('category_slug', $category['slug']);

                // Artiklar utan sektion först, därefter varje sektion i bokstavsordning.
                $sections = $inCategory
                    ->groupBy(fn (array $a) => $a['section_title'] ?? '')
                    ->sortKeys()
                    ->map(fn ($group, $title) => ['title' => $title ?: null, 'articles' => $group->values()->all()])
                    ->values()
                    ->all();

                return $category + ['article_count' => $inCategory->count(), 'sections' => $sections];
            })
            ->sortBy([['order', 'asc'], ['title', 'asc']])
            ->values()
            ->all();

        return $result;
    }

    /**
     * Intercom lägger översättningar i translated_content.<språk>. Använd
     * önskat språk om det finns och har innehåll, annars grundtexten.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function localized(array $item, string $locale): array
    {
        $translated = $item['translated_content'][$locale] ?? null;

        if (is_array($translated) && filled($translated['title'] ?? $translated['name'] ?? null)) {
            return $translated + $item;
        }

        return $item;
    }

    /**
     * Grov typ av artikel utifrån titeln, så korten kan få en passande ikon
     * (plug = integration, upload = import, download = export, doc = övrigt).
     */
    protected function kindOf(string $title): string
    {
        $t = mb_strtolower($title);

        return match (true) {
            str_contains($t, 'integration') => 'plug',
            str_contains($t, 'import') => 'upload',
            str_contains($t, 'export') => 'download',
            default => 'doc',
        };
    }

    protected function uniqueSlug(string $title, string $id, array $taken): string
    {
        $base = Str::slug($title) ?: 'artikel';

        return in_array($base, $taken, true) ? $base.'-'.$id : $base;
    }

    protected function plain(?string $text): ?string
    {
        $text = $this->text(strip_tags((string) $text));

        return $text === '' ? null : $text;
    }

    /**
     * Intercom skickar titlar och namn med HTML-entiteter (t.ex. "Bank &amp;
     * Skatt"). Avkodas här; Antlers escapar texten igen när den visas.
     */
    protected function text(?string $text): string
    {
        return trim(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Artikeltexten är HTML från Intercom. Den kommer från vår egen
     * arbetsyta, men rensas ändå (skript, händelseattribut, iframes osv.)
     * innan den visas på sajten.
     */
    protected function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // DOMSanitizer läser HTML utan teckenkodning och förvanskar annars å/ä/ö
        // (UTF-8 tolkas som Latin-1). Icke-ASCII-tecken skickas därför som
        // numeriska entiteter, som tolkas rätt och kommer tillbaka som riktiga tecken.
        $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $sanitizer = new DOMSanitizer(DOMSanitizer::HTML);

        // Intercoms inbyggda färgstilar (t.ex. på tipsrutor) ska inte styra
        // utseendet här - sajtens egen CSS gör det.
        $sanitizer->addDisallowedAttributes(['style']);

        return $this->leadSentencesToParagraphs($sanitizer->sanitize($html, ['compress-output' => false]));
    }

    /**
     * Redaktörer använder ibland en rubrik (h2-h4) för en hel inledande mening,
     * t.ex. "Importguiden hittar du under Inställningar i Oqto. Om du istället
     * ...". En lång mening är ingen rubrik - den blir ett stycke med klassen
     * support-lead (lite större, vanlig vikt) i stället för en tung rubrik.
     */
    protected function leadSentencesToParagraphs(string $html): string
    {
        return preg_replace_callback(
            '~<h([2-4])\b[^>]*>(.*?)</h\1>~s',
            function (array $m) {
                $plain = trim(strip_tags($m[2]));

                return mb_strlen($plain) > 80 ? '<p class="support-lead">'.trim($m[2]).'</p>' : $m[0];
            },
            $html,
        );
    }

    /**
     * @return array{categories: array<int, array<string, mixed>>, articles: array<int, array<string, mixed>>, synced_at: ?string}
     */
    protected function empty(): array
    {
        return ['categories' => [], 'articles' => [], 'synced_at' => null];
    }
}
