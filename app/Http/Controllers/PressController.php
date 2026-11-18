<?php

namespace App\Http\Controllers;

use App\Services\Press\PressCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Statamic\View\View;
use XMLWriter;

/**
 * Pressrummet: listan på /press (med kategorifilter) och RSS-flödet på /press/feed.xml.
 * Själva pressmeddelandena är poster i samlingen "press" och visas av Statamic på
 * /press/{slug}. Utkast och framtida datum syns varken här eller i flödet.
 */
class PressController extends Controller
{
    public function __construct(protected PressCatalog $press) {}

    public function index(Request $request): View
    {
        $category = $request->query('kategori');
        $category = is_string($category) && array_key_exists($category, PressCatalog::CATEGORIES) ? $category : null;

        $items = $this->press->published($category)->map(fn ($entry) => [
            'title' => (string) $entry->get('title'),
            'url' => $entry->url(),
            'date' => $entry->date(),
            'category_label' => $this->press->categoryLabel($entry),
            'summary' => $this->press->summary($entry),
            'cover_url' => $this->press->coverUrl($entry),
        ])->values()->all();

        $filters = collect(['' => 'Alla'] + PressCatalog::CATEGORIES)->map(fn ($label, $key) => [
            'label' => $label,
            'url' => $key === '' ? '/press' : '/press?kategori='.$key,
            'active' => ($key === '' && $category === null) || $key === $category,
        ])->values()->all();

        return View::make('press/index')->layout('layout')->with([
            'title' => 'Press',
            'seo_title' => 'Press',
            'seo_description' => 'Nyheter, pressmeddelanden och presskontakter från Oqto.',
            'feed_url' => $this->press->feedUrl(),
            'items' => $items,
            'filters' => $filters,
            'has_items' => $items !== [],
            'filtered' => $category !== null,
        ]);
    }

    /** RSS 2.0 med de 20 senaste publicerade posterna (LinkedIn-texten ligger i content:encoded och oqto:linkedinCaption). */
    public function feed(): Response
    {
        $origin = $this->press->origin();
        $items = $this->press->published(null, PressCatalog::FEED_LIMIT);

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->writeAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
        $xml->writeAttribute('xmlns:oqto', 'https://oqto.se/ns/press');

        $xml->startElement('channel');
        $xml->writeElement('title', 'Oqto – Press');
        $xml->writeElement('link', $origin.'/press');
        $xml->writeElement('description', 'Nyheter och pressmeddelanden från Oqto.');
        $xml->writeElement('language', 'sv-se');

        $xml->startElementNs('atom', 'link', null);
        $xml->writeAttribute('href', $this->press->feedUrl());
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        if ($latest = $items->first()) {
            $xml->writeElement('lastBuildDate', $this->rfc822($latest->date()));
        }

        foreach ($items as $entry) {
            $url = $origin.$entry->url();
            $cover = $this->press->cover($entry);
            $coverUrl = $origin.$cover['url'];
            $caption = $this->press->linkedinCaption($entry);

            $xml->startElement('item');
            $xml->writeElement('title', (string) $entry->get('title'));
            $xml->writeElement('link', $url);

            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'true');
            $xml->text($url);
            $xml->endElement();

            $xml->writeElement('pubDate', $this->rfc822($entry->date()));
            $xml->writeElement('description', $this->press->summary($entry));
            $xml->writeElement('category', $this->press->categoryLabel($entry));

            $xml->startElementNs('content', 'encoded', null);
            $xml->writeCdata($this->cdataSafe($caption));
            $xml->endElement();

            $xml->startElementNs('oqto', 'linkedinCaption', null);
            $xml->writeCdata($this->cdataSafe($caption));
            $xml->endElement();

            $xml->startElement('enclosure');
            $xml->writeAttribute('url', $coverUrl);
            $xml->writeAttribute('length', (string) $cover['length']);
            $xml->writeAttribute('type', $cover['type']);
            $xml->endElement();

            $xml->startElementNs('media', 'content', null);
            $xml->writeAttribute('url', $coverUrl);
            $xml->writeAttribute('medium', 'image');
            $xml->writeAttribute('type', $cover['type']);
            $xml->endElement();

            $xml->endElement(); // item
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /** RFC 822 (med fyrsiffrigt år, som RSS 2.0 tillåter), t.ex. "Tue, 03 Nov 2026 09:30:00 +0100". */
    protected function rfc822(\DateTimeInterface $date): string
    {
        return $date->format('D, d M Y H:i:s O');
    }

    /** "]]>" avslutar en CDATA-sektion, så det delas upp. */
    protected function cdataSafe(string $text): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $text);
    }
}
