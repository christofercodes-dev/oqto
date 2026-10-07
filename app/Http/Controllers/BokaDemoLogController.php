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
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ]);

        $logger->log(
            'company_name',
            null,
            trim($validated['name']),
            (int) $validated['alternative'],
            (string) $request->ip(),
            $request->only(['utm_source', 'utm_medium', 'utm_campaign']),
        );

        return response()->noContent();
    }
}
