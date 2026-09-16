<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
            // 'visibility' => 'public', // https://statamic.dev/assets#container-visibility
        ],

        // Bucket som en extern .NET-tjänst nattligen fyller med
        // applications.json + avatar-/mediabilder. Se app/Console/Commands/
        // ImportIntegrations.php. Bucketen är privat (ingen anonym läsning),
        // vilket bekräftats genom att en vanlig url() ger HTTP 403. Därför
        // sätts INGET "url" här - det gör att Statamic självt räknar
        // containern som privat (AssetContainer::accessible() kollar just
        // om disken har en "url"), så {{ avatar:url }} m.fl. korrekt
        // returnerar null istället för en trasig länk. Riktig visning ska
        // ske via modifiern App\Modifiers\SignedUrl ({{ avatar:signed_url }}),
        // som genererar en tillfällig, förhandssignerad S3-URL.
        //
        // Driver "resilient_s3" (registrerad i AppServiceProvider) bygger
        // en riktig S3-koppling om INTEGRATIONS_S3_BUCKET är satt, annars
        // en ofarlig tom lokal mapp - och gör i båda fallen filbläddring
        // ofarlig mot krascher (se app/Filesystem/ResilientListingAdapter.php).
        // Utan det skulle t.ex. Statamics egen stache-uppvärmning vid varje
        // deploy krascha helt om bucketen saknas, är fel konfigurerad eller
        // IAM-användaren saknar behörighet.
        'integrations_s3' => [
            'driver' => 'resilient_s3',
            'key' => env('INTEGRATIONS_S3_KEY', ''),
            'secret' => env('INTEGRATIONS_S3_SECRET', ''),
            'region' => env('INTEGRATIONS_S3_REGION', 'eu-north-1'),
            'bucket' => env('INTEGRATIONS_S3_BUCKET'),
            // Bucketen delar samma S3-bucket mellan miljöer (t.ex.
            // .../production, .../staging) - detta prefixar alla
            // sökvägar (applications.json, images/) automatiskt.
            'root' => env('INTEGRATIONS_S3_ROOT_PREFIX'),
            // Sätts bara om ni i framtiden lägger en CDN (t.ex. CloudFront)
            // framför bucketen och gör den publik - då blir containern
            // "accessible" igen och {{ avatar:url }} fungerar direkt.
            'url' => env('INTEGRATIONS_S3_URL') ?: null,
            'throw' => false,
            'report' => false,
        ],

        'assets' => [
            'driver' => 'local',
            'root' => public_path('assets'),
            'url' => '/assets',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
