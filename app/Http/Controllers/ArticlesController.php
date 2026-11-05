<?php

namespace App\Http\Controllers;

use Statamic\Facades\Entry;
use Statamic\View\View;

/**
 * /artiklar listar de publicerade artiklarna. Själva artiklarna är poster i
 * samlingen "artiklar" och visas av Statamic på /artiklar/{slug}.
 *
 * Så länge det inte finns någon publicerad artikel finns inte heller listan
 * (404), så att inget syns utåt förrän vi är redo att släppa det första.
 */
class ArticlesController extends Controller
{
    public function index(): View
    {
        $published = Entry::query()
            ->where('collection', 'artiklar')
            ->whereStatus('published')
            ->count();

        abort_if($published === 0, 404);

        return View::make('artiklar/index')->layout('layout')->with([
            'title' => 'Artiklar',
            'seo_title' => 'Artiklar för redovisningsbyråer',
            'seo_description' => 'Tips, guider och nyheter för redovisningsbyråer och redovisningskonsulter, från oss på Oqto.',
        ]);
    }
}
