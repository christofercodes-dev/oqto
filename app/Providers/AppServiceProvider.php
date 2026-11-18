<?php

namespace App\Providers;

use App\Filesystem\ResilientListingAdapter;
use App\Modifiers\SignedUrl;
use App\Services\Foretagare\FeatureCatalog;
use App\Services\Integrations\IntegrationLogos;
use App\Services\Press\PressCatalog;
use App\Tags\Ekosystem;
use App\Tags\Foretagare;
use App\Tags\Integrations;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelAwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection;

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
        Foretagare::register();
        Ekosystem::register();

        // {{ logo_url }} på varje integration: logotypens adress, tagen direkt från
        // sökvägen på posten och oberoende av Statamics (cachade) bildlista.
        Collection::computed('integrations', 'logo_url', fn ($entry, $value) => IntegrationLogos::url($entry));

        // Byråns färdiga inbäddningskod, visas (skrivskyddad, sparas aldrig) i byråns formulär.
        Collection::computed('agencies', 'embed_code', fn ($entry, $value) => app(FeatureCatalog::class)->embedCode($entry->slug()));

        $this->registerPress();

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
    /**
     * Pressrummet: värden som mallarna och metataggarna använder direkt, och förvalda
     * presskontakter på nya pressmeddelanden.
     */
    protected function registerPress(): void
    {
        $press = fn (string $method, mixed ...$args) => fn ($entry, $value) => app(PressCatalog::class)->{$method}($entry, ...$args);

        Collection::computed('press', 'category_label', $press('categoryLabel'));
        Collection::computed('press', 'cover_url', $press('coverUrl'));
        Collection::computed('press', 'photo_url', $press('photoUrl'));
        Collection::computed('press', 'contact_list', $press('contacts'));
        Collection::computed('press', 'summary_text', $press('summary'));
        Collection::computed('press', 'feed_url', fn () => app(PressCatalog::class)->feedUrl());
        Collection::computed('press', 'og_type', fn () => 'article');
        // Delningskortet (LinkedIn, Slack m.fl.): layouten läser seo_title, seo_description och share_image.
        Collection::computed('press', 'seo_title', $press('socialTitle'));
        Collection::computed('press', 'seo_description', $press('socialDescription'));
        Collection::computed('press', 'share_image', $press('coverUrl', true));

        // Nya pressmeddelanden får teamets förvalda presskontakter ifyllda. Redan sparade
        // poster rörs inte, så en medvetet borttagen kontakt kommer inte tillbaka.
        Event::listen(EntryBlueprintFound::class, function (EntryBlueprintFound $event) {
            if ($event->blueprint->namespace() !== 'collections.'.PressCatalog::COLLECTION) {
                return;
            }

            // Statamic skickar händelsen utan post när blueprinten hämtas för ett nytt formulär,
            // och med posten när en befintlig öppnas. Blueprintobjektet kan delas mellan de två,
            // så vid redigering nollställs förvalet uttryckligen i stället för att bara hoppas över.
            $default = $event->entry === null ? app(PressCatalog::class)->defaultContactIds() : null;

            $event->blueprint->ensureFieldHasConfig('press_contacts', ['default' => $default]);
        });
    }
}
