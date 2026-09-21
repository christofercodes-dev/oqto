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
        ],
    ],

    'integrations_s3' => [
        // Nyckeln till JSON-filen som .NET-tjänsten dumpar nattligen.
        // Anslutningsuppgifterna för själva bucketen finns i
        // config/filesystems.php under disken "integrations_s3".
        'json_path' => env('INTEGRATIONS_S3_JSON_PATH', 'applications.json'),
    ],

];
