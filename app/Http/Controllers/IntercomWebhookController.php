<?php

namespace App\Http\Controllers;

use App\Services\Intercom\HelpCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tar emot webhooks från Intercom (POST /webhooks/intercom).
 *
 * Varje anrop är signerat: Intercom skickar headern
 * "X-Hub-Signature: sha1=<hmac>" där hmac är HMAC-SHA1 av råa body med
 * appens client secret som nyckel. Osignerade eller felsignerade anrop
 * avvisas. Giltiga anrop (utom Intercoms "ping" vid registreringen)
 * läser om hjälpcentret efter att svaret skickats, så Intercom får sitt
 * 200 direkt - och flera anrop i följd slås ihop till en omhämtning.
 */
class IntercomWebhookController extends Controller
{
    public function __invoke(Request $request, HelpCenter $helpCenter): Response
    {
        $secret = config('services.intercom.client_secret');

        if (blank($secret)) {
            Log::error('Intercom-webhook: INTERCOM_CLIENT_SECRET saknas i .env.');

            return response('Webhook är inte konfigurerad.', 503);
        }

        $expected = 'sha1='.hash_hmac('sha1', $request->getContent(), (string) $secret);
        $received = (string) $request->header('X-Hub-Signature', '');

        if (! hash_equals($expected, $received)) {
            return response('Ogiltig signatur.', 401);
        }

        $topic = (string) $request->input('topic', '');

        if ($topic === 'ping') {
            return response('OK', 200);
        }

        // Flera ändringar på kort tid (t.ex. en publicering som skickar
        // flera händelser) ger bara en omhämtning var 30:e sekund.
        if (Cache::add('intercom.webhook.refresh-lock', true, 30)) {
            dispatch(function () use ($helpCenter, $topic) {
                try {
                    $helpCenter->refresh();
                    Log::info("Intercom-webhook ({$topic}): hjälpcentret omhämtat.");
                } catch (Throwable $e) {
                    Log::error("Intercom-webhook ({$topic}): omhämtningen misslyckades: ".$e->getMessage());
                }
            })->afterResponse();
        }

        return response('OK', 200);
    }
}
