<?php

namespace App\Http\Controllers;

use App\Services\Foretagare\FeatureCatalog;
use Statamic\View\View;

/**
 * Byråernas sidor: /byra/{byrå} listar de funktioner byrån valt, och
 * /byra/{byrå}/{funktion} visar samma innehåll som /for-foretagare/{funktion}
 * men med byråns logga och färger. Innehållet ligger alltid kvar på
 * funktionsposten under "För företagare" - här läggs bara byråns profil på.
 */
class AgencyController extends Controller
{
    public function __construct(protected FeatureCatalog $catalog) {}

    public function index(string $agency): View
    {
        $entry = $this->catalog->agency($agency);

        abort_if($entry === null, 404);

        $data = $this->catalog->agencyData($entry);

        return View::make('agency/index')->layout('layout')->with([
            'title' => $data['intro_title'],
            'seo_title' => $data['intro_title'],
            'seo_description' => $data['intro_text'] ?: "Funktioner du som är kund hos {$data['name']} kan ta del av.",
            'noindex' => true,
            'title_suffix' => $data['name'].' · Oqto',
            'agency' => $data,
        ]);
    }

    public function feature(string $agency, string $feature): View
    {
        $agencyEntry = $this->catalog->agency($agency);

        abort_if($agencyEntry === null, 404);

        $featureEntry = $this->catalog->feature($feature, $agencyEntry);

        abort_if($featureEntry === null, 404);

        // Funktionens egna fält (title, benefits, screens ...) i samma form som
        // på /for-foretagare/{funktion}, plus byråns profil.
        return View::make('foretagare/show')->layout('layout')->with(
            $featureEntry->toAugmentedArray() + [
                // Byråernas sidor indexeras aldrig (se även config/seo.php för headern).
                'noindex' => true,
                'title_suffix' => (string) $agencyEntry->get('title').' · Oqto',
                'agency' => $this->catalog->agencyData($agencyEntry),
            ]
        );
    }
}
