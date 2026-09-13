<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bolagsverket - Värdefulla datamängder
    |--------------------------------------------------------------------------
    |
    | Bekräftat mot skarpa API-anrop (2026-09-08):
    | - Token: POST https://portal.api.bolagsverket.se/oauth2/token
    |   med scope "vardefulla-datamangder:read".
    | - Data: POST https://gw.api.bolagsverket.se/vardefulla-datamangder/v1/organisationer
    |   med body {"identitetsbeteckning": "<orgnr utan bindestreck>"}.
    | - SNI-koder returneras som rena siffror utan punkt, t.ex. "69201"
    |   (inte "69.20"), så prefixen nedan är också dotless.
    |
    */

    'client_id' => env('BOLAGSVERKET_CLIENT_ID'),
    'client_secret' => env('BOLAGSVERKET_CLIENT_SECRET'),

    'token_uri' => env(
        'BOLAGSVERKET_TOKEN_URI',
        'https://portal.api.bolagsverket.se/oauth2/token'
    ),

    'base_uri' => env(
        'BOLAGSVERKET_BASE_URI',
        'https://gw.api.bolagsverket.se/vardefulla-datamangder/v1'
    ),

    'scope' => env('BOLAGSVERKET_SCOPE', 'vardefulla-datamangder:read'),

    /*
    |--------------------------------------------------------------------------
    | SNI-branscher som ger Alternativ 1 (Cal.com-bokning)
    |--------------------------------------------------------------------------
    |
    | Om organisationens primära SNI-kod börjar med någon av dessa prefix
    | visas alternativ 1. Alla övriga organisationer (och organisationer
    | som inte kan slås upp) får alternativ 2.
    |
    | '692' matchar SNI-koder som 69201, 69202 osv. (redovisning, bokföring,
    | revision, skatterådgivning). '702' matchar 70210, 70220 osv.
    | (konsultverksamhet avseende företagsledning/organisation).
    |
    */

    'sni_prefixes_alternative_1' => [
        '692',
        '702',
    ],

];
