@php
    $firstName = trim(explode(' ', trim($recipientName))[0] ?? '');
    $date = $details['date'] ?? null;
    $dateShort = $details['date_short'] ?? null;
    $address = $details['address'] ?? null;
    $agenda = $details['agenda'] ?? [];

    $sans = "'Helvetica Neue',Helvetica,Arial,sans-serif";
    $mono = "'Courier New',Courier,monospace";
@endphp
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $attending ? 'Tack för din anmälan' : 'Tack för ditt svar' }}</title>
</head>
<body style="margin:0;padding:0;background:#cad7e6;font-family:{!! $sans !!};color:#1a1715;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#cad7e6;">
    <tr><td align="center" style="padding:32px 12px 48px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;">

            {{-- Logga + etikett --}}
            <tr><td style="padding:0 20px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td align="left" valign="middle">
                        <img src="{{ url('images/investor/oqto-logo-mail.png') }}" alt="Oqto" width="56" height="56" style="display:block;border:0;width:56px;height:56px;">
                    </td>
                    <td align="right" valign="middle" style="font-family:{!! $mono !!};font-size:14px;color:#3a3531;">
                        {{ $attending ? 'Bekräftelse' : 'Tack för ditt svar' }}
                    </td>
                </tr></table>
            </td></tr>

            {{-- Orange hero --}}
            <tr><td style="background:#ed6e36;border-radius:20px 20px 0 0;padding:44px 40px 48px;">
                <p style="margin:0 0 18px;font-family:{!! $mono !!};font-size:14px;color:#3a2a20;">
                    Investerarträff{{ $dateShort ? ' · '.$dateShort : '' }}
                </p>
                <h1 style="margin:0 0 20px;font-family:{!! $sans !!};font-size:42px;line-height:1.08;font-weight:700;letter-spacing:-1.5px;color:#1a1715;">
                    {{ $attending ? 'Tack för din anmälan!' : 'Tack för ditt svar' }}
                </h1>
                <p style="margin:0;font-size:18px;line-height:1.55;color:#1a1715;">
                    @if ($attending)
                        Vi ser fram emot att ses{{ $dateShort ? ' den '.$dateShort : '' }}.
                    @else
                        Vi har tagit emot ditt svar och återkommer med information.
                    @endif
                </p>
            </td></tr>

            {{-- Vit innehållsdel --}}
            <tr><td style="background:#ffffff;border-radius:0 0 20px 20px;padding:40px 40px 44px;">

                <p style="margin:0 0 20px;font-size:17px;line-height:1.6;">Hej{{ $firstName ? ' '.$firstName : '' }}!</p>

                @if ($attending)
                    <p style="margin:0 0 28px;font-size:17px;line-height:1.6;">
                        @if ($invitedBy)
                            {{ $invitedBy }} har anmält dig till Investerarträff för Oqto. Här är det viktigaste om kvällen.
                        @else
                            Din anmälan till Investerarträff för Oqto är registrerad. Här är det viktigaste om kvällen.
                        @endif
                    </p>

                    @if ($date || $address)
                        <p style="margin:0 0 8px;font-family:{!! $mono !!};font-size:14px;color:#7a746f;">Tid och plats</p>
                        <p style="margin:0 0 28px;font-size:22px;line-height:1.35;font-weight:600;letter-spacing:-0.3px;">
                            @if ($date){{ $date }}<br>@endif
                            @if ($address)
                                @if (! empty($details['map_url']))
                                    <a href="{{ $details['map_url'] }}" style="color:#1a1715;font-weight:400;font-size:17px;text-decoration:underline;">{{ $address }}</a>
                                @else
                                    <span style="font-weight:400;font-size:17px;">{{ $address }}</span>
                                @endif
                            @endif
                        </p>
                    @endif

                    @if (count($agenda))
                        <p style="margin:0 0 12px;font-family:{!! $mono !!};font-size:14px;color:#7a746f;">Agenda</p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
                            @foreach ($agenda as $row)
                                <tr>
                                    <td valign="top" style="padding:8px 16px 8px 0;border-top:1px solid #ece8e3;font-family:{!! $mono !!};font-size:14px;color:#7a746f;white-space:nowrap;">{{ $row['time'] }}</td>
                                    <td valign="top" style="padding:8px 0;border-top:1px solid #ece8e3;font-size:17px;font-weight:600;">{{ $row['title'] }}</td>
                                </tr>
                            @endforeach
                            <tr><td colspan="2" style="border-top:1px solid #ece8e3;font-size:0;line-height:0;">&nbsp;</td></tr>
                        </table>
                    @endif

                    <p style="margin:0 0 8px;font-family:{!! $mono !!};font-size:14px;color:#7a746f;">Dina svar</p>
                    <p style="margin:0 0 28px;font-size:17px;line-height:1.7;color:#55504c;">
                        Middag på kvällen: <strong style="color:#1a1715;">{{ ($details['joins_dinner'] ?? null) === 'yes' ? 'Ja' : 'Nej' }}</strong><br>
                        Hjälp med boende{{ ! empty($details['stay_dates']) ? ' '.$details['stay_dates'] : '' }}: <strong style="color:#1a1715;">{{ ($details['needs_accommodation'] ?? null) === 'yes' ? 'Ja' : 'Nej' }}</strong>
                    </p>

                    <p style="margin:0 0 8px;font-size:17px;line-height:1.6;">Behöver du ändra något i din anmälan? Svara bara på det här mejlet.</p>
                @else
                    <p style="margin:0 0 8px;font-size:17px;line-height:1.6;">
                        Tack för att du svarade på inbjudan till Investerarträff för Oqto.
                        @if (! empty($details['info_preference']))
                            Vi hör av oss med information via det sätt du önskade ({{ mb_strtolower($details['info_preference']) }}).
                        @else
                            Vi hör av oss med information.
                        @endif
                    </p>
                @endif

                <p style="margin:28px 0 0;font-size:17px;line-height:1.6;">Vänliga hälsningar,<br><strong>Oqto</strong></p>

            </td></tr>

        </table>
    </td></tr>
</table>
</body>
</html>
