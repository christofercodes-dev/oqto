<?php

namespace Tests\Feature;

use App\Scopes\Logo;
use App\Services\Integrations\IntegrationLogos;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Tests\TestCase;

class IntegrationLogoTest extends TestCase
{
    protected const PREFIX = 'zz-test-logo-';

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
     * @param  array<string, mixed>  $data
     */
    protected function integration(string $slug, array $data = [], bool $published = true): EntryContract
    {
        $entry = Entry::make()
            ->collection('integrations')
            ->slug(self::PREFIX.$slug)
            ->published($published)
            ->data(array_merge(['title' => 'Logo '.$slug], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    /**
     * @param  iterable<EntryContract>  $entries
     * @return array<int, string>
     */
    protected function ours(iterable $entries): array
    {
        return collect($entries)
            ->map(fn (EntryContract $entry) => $entry->slug())
            ->filter(fn (string $slug) => str_starts_with($slug, self::PREFIX))
            ->map(fn (string $slug) => substr($slug, strlen(self::PREFIX)))
            ->sort()
            ->values()
            ->all();
    }

    protected function createSamples(): void
    {
        $this->integration('fran-kallan', ['avatar' => ['images/abc.png']]);
        $this->integration('egen', ['custom_avatar' => ['integrationer/egen.png']]);
        $this->integration('ingen');
        $this->integration('tom-lista', ['avatar' => [], 'custom_avatar' => null]);
        $this->integration('tom-text', ['avatar' => ['  ']]);
        $this->integration('utkast-utan', published: false);
    }

    public function test_a_logo_from_the_source_or_an_uploaded_one_counts(): void
    {
        $this->createSamples();

        $this->assertSame(
            ['ingen', 'tom-lista', 'tom-text', 'utkast-utan'],
            $this->ours(IntegrationLogos::missing())
        );

        // Efter importen räknas bara det som visas på sajten.
        $this->assertSame(
            ['ingen', 'tom-lista', 'tom-text'],
            $this->ours(IntegrationLogos::missing(onlyPublished: true))
        );
    }

    public function test_the_missing_logos_come_in_alphabetical_order(): void
    {
        $this->integration('b', ['title' => 'Beta']);
        $this->integration('a', ['title' => 'alfa']);
        $this->integration('c', ['title' => 'Cesar']);

        $titles = IntegrationLogos::missing()
            ->filter(fn (EntryContract $entry) => str_starts_with($entry->slug(), self::PREFIX))
            ->map(fn (EntryContract $entry) => $entry->get('title'))
            ->all();

        $this->assertSame(['alfa', 'Beta', 'Cesar'], array_values($titles));
    }

    public function test_the_control_panel_filter_lists_integrations_without_a_logo(): void
    {
        $this->createSamples();

        $filter = fn (string $value) => $this->ours(tap(
            Entry::query()->where('collection', 'integrations'),
            fn ($query) => (new Logo)->apply($query, ['logo' => $value])
        )->get());

        $this->assertSame(['ingen', 'tom-lista', 'tom-text', 'utkast-utan'], $filter('saknas'));
        $this->assertSame(['egen', 'fran-kallan'], $filter('finns'));

        $this->assertSame('Logotyp: saknas', (new Logo)->badge(['logo' => 'saknas']));
        $this->assertSame('Logotyp: finns', (new Logo)->badge(['logo' => 'finns']));
    }

    public function test_the_filter_is_only_offered_on_the_integrations_list(): void
    {
        $this->assertTrue((new Logo)->context(['collection' => 'integrations'])->visibleTo('entries'));
        $this->assertFalse((new Logo)->context(['collection' => 'pages'])->visibleTo('entries'));
        $this->assertFalse((new Logo)->context(['collection' => 'integrations'])->visibleTo('terms'));
        $this->assertFalse((new Logo)->visibleTo('entries'));
    }

    public function test_an_imported_integration_shows_an_uploaded_logo_when_the_source_has_none(): void
    {
        // Som E-revisor: kommer från källan (har external_id) men utan avatar.
        $entry = $this->integration('importerad', [
            'external_id' => '00000000-test-logo-0000-000000000001',
            'short_description' => 'Kort text',
            'custom_avatar' => ['byraer/digim.png'],
        ]);

        $this->get($entry->url())
            ->assertOk()
            ->assertDontSee('integration-show-hero-image-placeholder', false)
            ->assertSee('<img src="/assets/byraer/digim.png" alt="Logo importerad">', false);

        $this->assertTrue(IntegrationLogos::has($entry));
    }

    public function test_the_logo_shows_even_when_statamics_cached_asset_list_does_not_know_the_file(): void
    {
        // Som när nattens import tar med en ny logotyp men Statamics cachade bildlista är gammal:
        // filen finns inte i listan, men sökvägen på posten räcker för att visa den.
        $entry = $this->integration('ny-logga', [
            'external_id' => '00000000-test-logo-0000-000000000002',
            'short_description' => 'Kort text',
            'avatar' => ['images/finns-inte-i-listan.png'],
        ]);

        $this->assertNull(\Statamic\Facades\Asset::find('integrations::images/finns-inte-i-listan.png'));
        $this->assertStringContainsString('images/finns-inte-i-listan.png', (string) IntegrationLogos::url($entry));

        // Detaljsidan visar bilden, inte bokstaven.
        $page = $this->get($entry->url())->assertOk();
        $page->assertDontSee('integration-show-hero-image-placeholder', false);
        $page->assertSee('images/finns-inte-i-listan.png', false);

        // Det gör kortet i listan också.
        $this->get('/integrationer')->assertOk()->assertSee('images/finns-inte-i-listan.png', false);
    }

    public function test_no_logo_means_no_url_and_the_letter_is_shown(): void
    {
        $entry = $this->integration('bokstav', ['short_description' => 'Kort text']);

        $this->assertNull(IntegrationLogos::url($entry));

        $this->get($entry->url())->assertOk()->assertSee('integration-show-hero-image-placeholder', false);
    }

    public function test_the_import_never_touches_an_uploaded_logo(): void
    {
        $source = file_get_contents(app_path('Services/Integrations/IntegrationsImporter.php'));

        // Importen skriver bara "avatar" (från avatarKey); "custom_avatar" är redaktörens eget fält.
        $this->assertStringNotContainsString("'custom_avatar'", $source);
    }
}
