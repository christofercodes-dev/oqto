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
        // integrations.json + avatar-/mediabilder. Se app/Console/Commands/
        // ImportIntegrations.php. Om bucketen inte är publikt läsbar
        // behöver "visibility" sättas till "private" och "url" pekas mot
        // t.ex. en CloudFront-distribution eller signerade URL:er.
        //
        // Så länge INTEGRATIONS_S3_BUCKET inte är satt pekar disken mot en
        // ofarlig, tom lokal mapp istället för S3. Utan det skulle varje
        // artisan-kommando som rör asset-containern (t.ex. Statamics egen
        // stache-uppvärmning vid deploy) krascha hårt - AWS accepterar inte
        // en tom bucket-parameter, och det stoppar hela deployen. Sätt bara
        // riktiga INTEGRATIONS_S3_*-värden i .env när bucketen finns, så
        // växlar den automatiskt över till den riktiga S3-disken.
        'integrations_s3' => env('INTEGRATIONS_S3_BUCKET')
            ? [
                'driver' => 's3',
                'key' => env('INTEGRATIONS_S3_KEY', ''),
                'secret' => env('INTEGRATIONS_S3_SECRET', ''),
                'region' => env('INTEGRATIONS_S3_REGION', 'eu-north-1'),
                'bucket' => env('INTEGRATIONS_S3_BUCKET'),
                'url' => env('INTEGRATIONS_S3_URL'),
                'throw' => false,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/integrations-s3-unconfigured'),
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
