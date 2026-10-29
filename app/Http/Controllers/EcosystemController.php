<?php

namespace App\Http\Controllers;

use Statamic\View\View;

/**
 * /integrationer/ekosystem listar alla ekosystem. Själva ekosystemen är poster
 * i samlingen "ekosystem" och visas av Statamic på /integrationer/ekosystem/{slug}.
 */
class EcosystemController extends Controller
{
    public function index(): View
    {
        return View::make('ekosystem/index')->layout('layout')->with([
            'title' => 'Ekosystem',
            'seo_title' => 'Ekosystem – integrationer som fungerar tillsammans',
            'seo_description' => 'Se hur flera integrationer samverkar med Oqto och varandra, steg för steg, från faktura till matchad betalning.',
        ]);
    }
}
