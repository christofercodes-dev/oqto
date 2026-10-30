<?php

namespace Tests\Feature;

use App\Services\Integrations\IntegrationsImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

/**
 * En integration som kommit till i bucketen efter senaste deploy ska få sin
 * logga efter nattens import, inte först vid nästa deploy.
 */
class IntegrationAssetListingTest extends TestCase
{
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/integrations-listing-'.uniqid());

        File::ensureDirectoryExists($this->root.'/images');
        File::put($this->root.'/images/gammal.png', 'x');

        // Bucketen byts mot en lokal mapp så att testet aldrig når S3.
        config(['filesystems.disks.integrations_s3' => ['driver' => 'local', 'root' => $this->root]]);
        Storage::forgetDisk('integrations_s3');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_new_image_is_found_after_the_listing_is_refreshed(): void
    {
        $listing = fn () => AssetContainer::find('integrations')->contents()->all()->keys()->all();

        // Listan cachas (för alltid) första gången någon frågar efter en bild.
        $this->assertContains('images/gammal.png', $listing());

        File::put($this->root.'/images/ny.png', 'y');

        // Utan omräkning är den nya bilden okänd, och {{ if avatar }} blir falsk.
        $stale = (new \Statamic\Assets\AssetContainerContents)->container(AssetContainer::find('integrations'));
        $this->assertNotContains('images/ny.png', $stale->all()->keys()->all());

        app(IntegrationsImporter::class)->refreshAssetListing();

        $fresh = (new \Statamic\Assets\AssetContainerContents)->container(AssetContainer::find('integrations'));
        $this->assertContains('images/ny.png', $fresh->all()->keys()->all());
    }

    public function test_an_empty_listing_is_not_cached(): void
    {
        File::deleteDirectory($this->root.'/images');

        app(IntegrationsImporter::class)->refreshAssetListing();

        $this->assertFalse(Cache::has('asset-list-contents-integrations'));
    }

    public function test_the_listing_is_rebuilt_at_six_every_morning_and_on_demand(): void
    {
        // Listan cachas första gången någon frågar efter den.
        $this->assertContains('images/gammal.png', AssetContainer::find('integrations')->contents()->all()->keys()->all());

        File::put($this->root.'/images/ny-morgon.png', 'z');

        $this->assertNotContains('images/ny-morgon.png', (new \Statamic\Assets\AssetContainerContents)->container(AssetContainer::find('integrations'))->all()->keys()->all());

        // Ligger i schemat som en morgonjobb efter nattens import ...
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'integrations:refresh-assets'));

        $this->assertNotNull($event, 'Jobbet integrations:refresh-assets saknas i schemat.');
        $this->assertSame('0 6 * * *', $event->expression);

        // ... och kan köras för hand.
        $this->artisan('integrations:refresh-assets')->assertSuccessful();

        $this->assertContains('images/ny-morgon.png', (new \Statamic\Assets\AssetContainerContents)->container(AssetContainer::find('integrations'))->all()->keys()->all());
    }
}
