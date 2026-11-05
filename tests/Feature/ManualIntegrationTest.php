<?php

namespace Tests\Feature;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ManualIntegrationTest extends TestCase
{
    protected const PREFIX = 'zz-test-manuell-';

    /**
     * @var array<int, EntryContract>
     */
    protected array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $entry) {
            $entry->delete();
        }

        parent::tearDown();
    }

    /**
     * En integration som lagts in för hand i CP:n: inget external_id.
     *
     * @param  array<string, mixed>  $data
     */
    protected function manual(string $slug, array $data = []): EntryContract
    {
        $entry = Entry::make()
            ->collection('integrations')
            ->slug(self::PREFIX.$slug)
            ->published(true)
            ->data(array_merge(['title' => 'Manuell '.$slug, 'short_description' => 'Kort text'], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    public function test_a_manual_integration_shows_its_own_logo_and_gallery(): void
    {
        $entry = $this->manual('med-bilder', [
            'custom_avatar' => ['byraer/digim.png'],
            'custom_media' => ['byraer/digim.png'],
        ]);

        $page = $this->get($entry->url())->assertOk();

        $page->assertDontSee('integration-show-hero-image-placeholder', false);
        $page->assertSee('<img src="/assets/byraer/digim.png" alt="Manuell med-bilder">', false);
        $page->assertSee('integration-show-gallery-item', false);
    }

    public function test_a_manual_integration_without_images_falls_back_to_the_letter(): void
    {
        $entry = $this->manual('utan-bilder');

        $this->get($entry->url())
            ->assertOk()
            ->assertSee('integration-show-hero-image-placeholder', false)
            ->assertDontSee('integration-show-gallery-section', false);
    }
}
