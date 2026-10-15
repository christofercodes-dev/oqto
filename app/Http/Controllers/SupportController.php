<?php

namespace App\Http\Controllers;

use App\Services\Intercom\HelpCenter;
use App\Services\Status\AppStatusChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Statamic\View\View;

/**
 * Supportsidorna: /support (status, kontakt och flytt-guider), kategorisidor och
 * artikelsidor. Artiklarna kommer från Intercom via App\Services\Intercom\HelpCenter.
 */
class SupportController extends Controller
{
    public function __construct(protected HelpCenter $helpCenter) {}

    /**
     * Supportsidan (/support): driftstatus för appen, vägar till kontakt om
     * något strular, och guiderna för att flytta till Oqto. Själva statusen
     * hämtas av sidans skript från statusCheck() med jämna mellanrum.
     */
    public function index(): View
    {
        $data = $this->helpCenter->data();

        // Flytt-artiklarna: kategorin vars namn nämner flytt/byte, annars de första artiklarna.
        $moveCategory = collect($data['categories'])
            ->first(fn (array $c) => preg_match('/flytt|byt/iu', $c['title']) === 1);

        // Två utvalda guider visas bredvid varandra; resten nås via "Se alla guider".
        $featuredIds = array_map('strval', (array) config('services.intercom.featured_article_ids', []));

        $featured = collect($featuredIds)
            ->map(fn (string $id) => collect($data['articles'])->firstWhere('id', $id))
            ->filter()
            ->values();

        if ($featured->isEmpty()) {
            $featured = collect($moveCategory
                ? collect($moveCategory['sections'])->flatMap(fn (array $s) => $s['articles'])->all()
                : $data['articles']);
        }

        $moveArticles = $featured->take(2)->all();

        return $this->view('support/index', [
            'title' => 'Support',
            'seo_description' => 'Support för Oqto: se om allt fungerar just nu, kontakta oss om något strular och hitta guider för att flytta till Oqto.',
            // Bara det som redan finns i cachen - sidan väntar aldrig på appen.
            // Saknas det hämtar sidans skript status direkt när den laddats.
            'initial_json' => json_encode(app(AppStatusChecker::class)->cached(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'status_url' => (string) parse_url((string) config('services.status.url'), PHP_URL_HOST),
            'move_sections' => [['title' => null, 'articles' => $moveArticles]],
            'has_move_articles' => $moveArticles !== [],
            'move_title' => $moveCategory['title'] ?? null,
            'move_url' => $moveCategory ? '/support/'.$moveCategory['slug'] : null,
        ]);
    }

    /**
     * Den gamla adressen /support/status gäller inte längre - statusen är en
     * del av /support.
     */
    public function status(): RedirectResponse
    {
        return redirect('/support#driftstatus', 301);
    }

    public function statusCheck(AppStatusChecker $checker): JsonResponse
    {
        return response()->json($checker->check())->header('Cache-Control', 'no-store');
    }

    public function category(string $category): View
    {
        $found = $this->helpCenter->category($category);

        abort_if($found === null || $found['article_count'] === 0, 404);

        return $this->view('support/category', [
            'title' => $found['title'],
            'seo_description' => $found['description'] ?? "Hjälpartiklar om {$found['title']} i Oqto.",
            'category' => $found,
        ]);
    }

    public function article(string $category, string $article): View
    {
        $found = $this->helpCenter->article($category, $article);

        abort_if($found === null, 404);

        $parent = $this->helpCenter->category($category);

        return $this->view('support/article', [
            'title' => $found['title'],
            'seo_description' => $found['description'] ?? "Hjälpartikel: {$found['title']}",
            'article' => $found,
            'category' => $parent,
            // Övriga artiklar i kategorin, till sidomenyn.
            'siblings' => collect($parent['sections'] ?? [])->flatMap(fn (array $s) => $s['articles'])->all(),
        ]);
    }

    protected function view(string $template, array $data): View
    {
        return View::make($template)->layout('layout')->with($data);
    }
}
