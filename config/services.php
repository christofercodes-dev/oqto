<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'zapier' => [
        'webhooks' => [
            'job_application' => env('ZAPIER_JOB_APPLICATION_WEBHOOK_URL'),
            'boka_demo' => env('ZAPIER_BOKA_DEMO_WEBHOOK_URL'),
            'contact' => env('ZAPIER_CONTACT_WEBHOOK_URL'),
            'developers_contact' => env('ZAPIER_DEVELOPERS_CONTACT_WEBHOOK_URL'),
            'boka_demo_lookup' => env('ZAPIER_BOKA_DEMO_LOOKUP_WEBHOOK_URL'),
            'investor_rsvp' => env('ZAPIER_INVESTOR_RSVP_WEBHOOK_URL'),
        ],
    ],

    'status' => [
        // Adressen som driftstatus-sidan (/support/status) kontrollerar.
        'url' => env('STATUS_APP_URL', 'https://app.oqto.se'),
        // Sekunder innan kontrollen ger upp (räknas som störning).
        'timeout' => (int) env('STATUS_TIMEOUT', 5),
        // Svarar appen men tar längre tid än så (ms) visas "långsammare än vanligt".
        'slow_ms' => (int) env('STATUS_SLOW_MS', 3000),
        // Resultatet återanvänds så här länge (sekunder), så att många besökare
        // som pollar sidan inte ger många anrop mot appen.
        'cache_seconds' => (int) env('STATUS_CACHE_SECONDS', 20),
    ],

    'intercom' => [
        // Hjälpartiklarna hämtas från Intercoms API och cachas (se
        // App\Services\Intercom\HelpCenter). Webhooken på
        // /webhooks/intercom nollställer cachen när något ändras.
        // Nycklarna läggs bara i .env (aldrig i koden eller git).
        'token' => env('INTERCOM_ACCESS_TOKEN'),
        'client_secret' => env('INTERCOM_CLIENT_SECRET'),
        // EU-arbetsytor: https://api.eu.intercom.io, Australien: https://api.au.intercom.io
        'base_url' => env('INTERCOM_API_URL', 'https://api.intercom.io'),
        'version' => env('INTERCOM_API_VERSION', '2.11'),
        // Språk som föredras när en artikel har flera översättningar.
        'locale' => env('INTERCOM_LOCALE', 'sv'),
        // Kommaseparerade id:n på samlingar (inkl. deras undersamlingar) som
        // ska visas, t.ex. "1445236" för bara "Flytta till Oqto". Tomt = alla
        // publicerade artiklar. Id:t syns i URL:en till samlingen i Intercom.
        'collection_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTERCOM_COLLECTION_IDS', ''))))),
        // Kommaseparerade artikel-id:n som visas som utvalda guider på /support
        // (i den ordningen). Id:t syns i artikelns URL i Intercom. Tomt = de
        // första två artiklarna i flytt-kategorin.
        'featured_article_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTERCOM_FEATURED_ARTICLE_IDS', ''))))),
        // Hur länge hjälpcentret cachas (sekunder) om ingen webhook kommer.
        'cache_ttl' => (int) env('INTERCOM_CACHE_TTL', 3600),
    ],

    'integrations_s3' => [
        // Nyckeln till JSON-filen som .NET-tjänsten dumpar nattligen.
        // Anslutningsuppgifterna för själva bucketen finns i
        // config/filesystems.php under disken "integrations_s3".
        'json_path' => env('INTEGRATIONS_S3_JSON_PATH', 'applications.json'),
    ],

];
