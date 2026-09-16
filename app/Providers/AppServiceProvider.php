<?php

namespace App\Providers;

use App\Filesystem\ResilientListingAdapter;
use App\Modifiers\SignedUrl;
use App\Tags\Integrations;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelAwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        SignedUrl::register();
        Integrations::register();

        // Egen disk-driver som slår in adaptern i ResilientListingAdapter,
        // så att en otillgänglig/skrivskyddad/ofullständigt konfigurerad
        // bucket aldrig kan krascha kringliggande kommandon (t.ex. Statamics
        // egen stache-uppvärmning vid deploy, eller ett försök att cacha
        // bildmetadata när en asset visas). Se config/filesystems.php
        // (disken "integrations_s3") och app/Filesystem/ResilientListingAdapter.php.
        Storage::extend('resilient_s3', function ($app, array $config) {
            $bucket = $config['bucket'] ?? null;

            if (! $bucket) {
                $adapter = new LocalFilesystemAdapter(
                    $config['root'] ?? storage_path('app/integrations-s3-unconfigured')
                );

                $resilientAdapter = new ResilientListingAdapter($adapter);

                return new LaravelFilesystemAdapter(
                    new Filesystem($resilientAdapter, ['throw' => $config['throw'] ?? false]),
                    $resilientAdapter,
                    $config
                );
            }

            $client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'] ?? 'eu-north-1',
                'credentials' => [
                    'key' => $config['key'] ?? '',
                    'secret' => $config['secret'] ?? '',
                ],
            ]);

            $adapter = new AwsS3V3Adapter($client, $bucket, $config['root'] ?? '');
            $resilientAdapter = new ResilientListingAdapter($adapter);

            // Måste vara Laravels egen AwsS3V3Adapter (inte bara en generisk
            // FilesystemAdapter) för att url()/temporaryUrl() ska fungera -
            // den klassen vet hur man bygger en S3-URL, den generiska
            // wrappern gör det bara för lokala/FTP-diskar.
            return new LaravelAwsS3V3Adapter(
                new Filesystem($resilientAdapter, ['throw' => $config['throw'] ?? false]),
                $resilientAdapter,
                $config,
                $client
            );
        });
    }
}
