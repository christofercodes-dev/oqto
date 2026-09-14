<?php

namespace App\Providers;

use App\Filesystem\ResilientListingAdapter;
use Aws\S3\S3Client;
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
        // Egen disk-driver som slår in adaptern i ResilientListingAdapter,
        // så att en otillgänglig/ofullständigt konfigurerad bucket aldrig
        // kan krascha kringliggande kommandon (t.ex. Statamics egen
        // stache-uppvärmning vid deploy). Se config/filesystems.php
        // (disken "integrations_s3") och app/Filesystem/ResilientListingAdapter.php.
        Storage::extend('resilient_s3', function ($app, array $config) {
            $bucket = $config['bucket'] ?? null;

            $adapter = $bucket
                ? new AwsS3V3Adapter(
                    new S3Client([
                        'version' => 'latest',
                        'region' => $config['region'] ?? 'eu-north-1',
                        'credentials' => [
                            'key' => $config['key'] ?? '',
                            'secret' => $config['secret'] ?? '',
                        ],
                    ]),
                    $bucket,
                    $config['root'] ?? ''
                )
                : new LocalFilesystemAdapter(
                    $config['root'] ?? storage_path('app/integrations-s3-unconfigured')
                );

            $resilientAdapter = new ResilientListingAdapter($adapter);

            return new LaravelFilesystemAdapter(
                new Filesystem($resilientAdapter, ['throw' => $config['throw'] ?? false]),
                $resilientAdapter,
                $config
            );
        });
    }
}
