<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Events\FormSubmitted;
use Throwable;

class SendFormSubmissionToZapier implements ShouldQueue
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
            // Inskicket är redan sparat i Statamic vid det här laget - ett
            // trasigt Zapier-webhook ska aldrig få det att se ut som att
            // formuläret misslyckades för besökaren.
            Log::warning("Kunde inte skicka \"{$handle}\"-inskick till Zapier: ".$e->getMessage());
        }
    }
}
