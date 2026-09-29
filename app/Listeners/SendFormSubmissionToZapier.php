<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Events\FormSubmitted;
use Throwable;

/**
 * Körs synkront (inte ShouldQueue) eftersom siten saknar en
 * köworker-daemon på Forge - en köad job hade annars bara lagts i
 * jobs-tabellen och aldrig körts. try/catch nedan gör att ett
 * trasigt Zapier-anrop ändå aldrig får formuläret att se ut som att
 * det misslyckades för besökaren.
 */
class SendFormSubmissionToZapier
{
    public function handle(FormSubmitted $event): void
    {
        $handle = $event->submission->form()->handle();

        $webhookUrl = config("services.zapier.webhooks.{$handle}");

        if (! $webhookUrl) {
            return;
        }

        $payload = array_merge(
            [
                'form' => $handle,
                'submitted_at' => $event->submission->date()->toIso8601String(),
            ],
            $event->submission->data()->all()
        );

        try {
            Http::timeout(10)->post($webhookUrl, $payload)->throw();
        } catch (Throwable $e) {
            Log::warning("Kunde inte skicka \"{$handle}\"-inskick till Zapier: ".$e->getMessage());
        }
    }
}
