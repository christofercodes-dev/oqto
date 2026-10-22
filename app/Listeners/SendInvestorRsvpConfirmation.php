<?php

namespace App\Listeners;

use App\Mail\InvestorRsvpConfirmation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\Entry;
use Throwable;

/**
 * Skickar ett tackmejl till varje deltagare som angetts i anmälan.
 * Körs synkront (som SendFormSubmissionToZapier) och ett misslyckat
 * mejl loggas bara - besökaren ska alltid se att anmälan gick igenom.
 */
class SendInvestorRsvpConfirmation
{
    public function handle(FormSubmitted $event): void
    {
        $submission = $event->submission;

        if ($submission->form()->handle() !== 'investor_rsvp') {
            return;
        }

        $attending = $submission->get('attending') === 'yes';
        $event_details = $this->eventDetails();

        $details = $event_details + [
            'joins_dinner' => $submission->get('joins_dinner'),
            'needs_accommodation' => $submission->get('needs_accommodation'),
            'info_preference' => $submission->get('info_preference'),
        ];

        $attendees = [
            [
                'name' => (string) $submission->get('attendee1_name'),
                'email' => (string) $submission->get('attendee1_email'),
                'invited_by' => null,
            ],
        ];

        if ($attending && filled($submission->get('attendee2_email'))) {
            $attendees[] = [
                'name' => (string) $submission->get('attendee2_name'),
                'email' => (string) $submission->get('attendee2_email'),
                'invited_by' => (string) $submission->get('attendee1_name'),
            ];
        }

        foreach ($attendees as $attendee) {
            if (blank($attendee['email'])) {
                continue;
            }

            try {
                Mail::to($attendee['email'], $attendee['name'] ?: null)->send(
                    new InvestorRsvpConfirmation($attendee['name'], $attending, $attendee['invited_by'], $details)
                );
            } catch (Throwable $e) {
                Log::warning('Kunde inte skicka tackmejl för investerarträffen: '.$e->getMessage());
            }
        }
    }

    /**
     * Datum, plats och agenda hämtas från sidan så att mejlet alltid stämmer med den.
     *
     * @return array<string, mixed>
     */
    protected function eventDetails(): array
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'investerartraff')->first();

        if (! $entry) {
            return [];
        }

        return [
            'date' => $entry->get('investor_date'),
            'date_short' => $entry->get('investor_date_short'),
            'address' => $entry->get('investor_address'),
            'map_url' => $entry->get('investor_map_url'),
            'stay_dates' => $entry->get('investor_stay_dates'),
            'agenda' => collect($entry->get('investor_agenda') ?? [])->map(fn ($row) => [
                'time' => $row['time'] ?? '',
                'title' => $row['title'] ?? '',
            ])->all(),
        ];
    }
}
