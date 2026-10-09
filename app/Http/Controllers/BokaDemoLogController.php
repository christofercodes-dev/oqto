<?php

namespace App\Http\Controllers;

use App\Services\BokaDemo\LeadLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tar emot bolagsnamn som besökaren skrivit in på /boka-demo (till skillnad
 * från organisationsnummer, som loggas direkt i uppslaget).
 */
class BokaDemoLogController extends Controller
{
    public function __invoke(Request $request, LeadLogger $logger): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'alternative' => ['required', 'integer', 'in:1,2'],
            'email' => ['required', 'email', 'max:190'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'gclid' => ['nullable', 'string', 'max:200'],
            'landing_page' => ['nullable', 'string', 'max:200'],
        ]);

        $logger->log(
            'company_name',
            null,
            trim($validated['name']),
            (int) $validated['alternative'],
            (string) $request->ip(),
            $request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'landing_page']),
            $validated['email'],
        );

        return response()->noContent();
    }
}
