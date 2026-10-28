<?php

namespace Tests\Feature;

use App\Services\Integrations\IntegrationsImporter;
use ReflectionMethod;
use Statamic\Facades\Entry;
use Tests\TestCase;

class IntegrationRatingTest extends TestCase
{
    protected const ID_PREFIX = 'rating-test-';

    protected function tearDown(): void
    {
        // Tar bara bort de integrationer testerna själva skapat.
        Entry::query()->where('collection', 'integrations')->get()
            ->filter(fn ($entry) => str_starts_with((string) $entry->get('external_id'), self::ID_PREFIX))
            ->each->delete();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function item(array $overrides = []): array
    {
        $id = self::ID_PREFIX.uniqid();

        return array_merge([
            'id' => $id,
            'name' => 'Betygstest '.$id,
            'shortDescription' => 'Test av betyg',
            'developerName' => 'Testutvecklare AB',
            'activationLevel' => 'company',
            'sniCodes' => [],
            'reasons' => [],
            'companyEmployeeRanges' => [],
            'companyRevenueRanges' => [],
            'mediaImageKeys' => [],
            'createdAt' => '2026-10-02T10:08:25.962875Z',
            'rating' => ['count' => 0, 'countLabel' => '0'],
            'reviews' => [],
        ], $overrides);
    }

    protected function import(array $item, $entry = null)
    {
        $importer = app(IntegrationsImporter::class);
        $method = new ReflectionMethod($importer, 'importItem');

        $method->invoke($importer, $item, $entry);

        return Entry::query()->where('collection', 'integrations')->where('external_id', $item['id'])->first();
    }

    public function test_a_rating_is_imported_and_shown_on_the_integration_page(): void
    {
        $entry = $this->import($this->item([
            'rating' => ['average' => 4.5, 'count' => 3, 'countLabel' => '3'],
        ]));

        $this->assertSame(4.5, $entry->get('rating_average'));
        $this->assertSame(3, $entry->get('rating_count'));

        $this->get($entry->url())
            ->assertOk()
            ->assertSee('integration-show-rating', false)
            ->assertSee('4,5')
            ->assertSee('(3 betyg)');
    }

    public function test_the_rating_is_shown_on_the_card_in_the_list(): void
    {
        $this->import($this->item(['rating' => ['average' => 5, 'count' => 1, 'countLabel' => '1']]));

        $this->get('/integrationer')
            ->assertOk()
            ->assertSee('integration-card-rating', false)
            ->assertSee('5,0');
    }

    public function test_nothing_is_shown_without_ratings(): void
    {
        $entry = $this->import($this->item());

        $this->assertNull($entry->get('rating_average'));
        $this->assertNull($entry->get('rating_count'));

        $this->get($entry->url())
            ->assertOk()
            ->assertDontSee('integration-show-rating', false);
    }

    public function test_a_missing_or_malformed_rating_does_not_break_the_import(): void
    {
        $withoutKey = $this->item();
        unset($withoutKey['rating'], $withoutKey['reviews'], $withoutKey['activationLevel']);

        $this->assertNull($this->import($withoutKey)->get('rating_count'));

        $this->assertNull($this->import($this->item(['rating' => 'inte ett objekt']))->get('rating_count'));

        $this->assertNull($this->import($this->item(['rating' => ['count' => 2]]))->get('rating_count'));
    }

    public function test_a_rating_is_removed_when_the_source_no_longer_has_one(): void
    {
        $item = $this->item(['rating' => ['average' => 4, 'count' => 2, 'countLabel' => '2']]);

        $entry = $this->import($item);
        $this->assertSame(2, $entry->get('rating_count'));

        $entry = $this->import(array_merge($item, ['rating' => ['count' => 0, 'countLabel' => '0']]), $entry);

        $this->assertNull($entry->get('rating_average'));
        $this->assertNull($entry->get('rating_count'));
    }
}
