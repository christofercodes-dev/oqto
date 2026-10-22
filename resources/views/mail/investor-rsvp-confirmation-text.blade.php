@php
    $firstName = trim(explode(' ', trim($recipientName))[0] ?? '');
@endphp
Hej{{ $firstName ? ' '.$firstName : '' }},

@if ($attending)
@if ($invitedBy)
{{ $invitedBy }} har anmält dig till Investerarträff för Oqto.
@else
Tack för din anmälan! Din anmälan till Investerarträff för Oqto är registrerad.
@endif

@if (! empty($details['date'])){{ $details['date'] }}
@endif
@if (! empty($details['address'])){{ $details['address'] }}
@endif
@if (count($details['agenda'] ?? []))

Agenda:
@foreach ($details['agenda'] as $row)
- {{ $row['time'] }} {{ $row['title'] }}
@endforeach
@endif

Middag på kvällen: {{ ($details['joins_dinner'] ?? null) === 'yes' ? 'Ja' : 'Nej' }}
Hjälp med boende: {{ ($details['needs_accommodation'] ?? null) === 'yes' ? 'Ja' : 'Nej' }}

Behöver du ändra något i din anmälan, svara bara på det här mejlet.
@else
Tack för att du svarade på inbjudan till Investerarträff för Oqto. Vi har tagit emot ditt svar och återkommer med information{{ ! empty($details['info_preference']) ? ' ('.mb_strtolower($details['info_preference']).')' : '' }}.
@endif

Vänliga hälsningar,
Oqto
