<?php

namespace Tests\Feature;

use App\Services\Ecosystem\EcosystemCatalog;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Tests\TestCase;

class EcosystemTest extends TestCase
{
    protected const PREFIX = 'zz-test-';

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

    protected function integration(string $slug): EntryContract
    {
        $entry = Entry::make()
            ->collection('integrations')
            ->slug(self::PREFIX.$slug)
            ->published(true)
            ->data(['title' => ucfirst($slug), 'short_description' => 'Om '.$slug]);

        $entry->save();

        return $this->created[] = $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function ecosystem(string $slug, array $data = [], bool $published = true): EntryContract
    {
        $entry = Entry::make()
            ->collection(EcosystemCatalog::COLLECTION)
            ->slug(self::PREFIX.$slug)
            ->published($published)
            ->data(array_merge([
                'title' => 'Ekosystem '.$slug,
                'headline' => 'Rubrik för '.$slug,
                'short_description' => 'Ingress för '.$slug,
                'systems' => [
                    ['key' => 'reda', 'name' => 'Reda', 'role' => 'Tolkning', 'tone' => 'blue'],
                    ['key' => 'hino', 'name' => 'Hino', 'role' => 'Attest', 'tone' => 'yellow', 'pos_x' => 80, 'pos_y' => 20],
                    ['key' => 'oanvant', 'name' => 'Oanvänt', 'tone' => 'gray'],
                ],
                'flows' => [
                    ['id' => 'a', 'type' => 'flow', 'enabled' => true, 'title' => 'Första flödet', 'steps' => [
                        ['system' => 'reda', 'title' => 'Tolkning', 'text' => 'Reda tolkar fakturan.', 'status' => 'Tolkad'],
                        ['system' => 'hino', 'with' => 'reda', 'title' => 'Attest', 'text' => 'Styrelsen attesterar.', 'status' => 'Attesterad'],
                    ]],
                ],
            ], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    public function test_ecosystem_page_renders_static_steps_and_data_for_the_script(): void
    {
        $entry = $this->ecosystem('a');

        $response = $this->get($entry->url())->assertOk();

        $response->assertSee('Rubrik för a', false);
        $response->assertSee('data-ecosystem', false);
        $response->assertSee('Första flödet', false);
        $response->assertSee('Styrelsen attesterar.', false);

        preg_match('#<script type="application/json" data-ecosystem-data>(.*?)</script>#s', $response->getContent(), $match);

        $payload = json_decode($match[1], true);

        $this->assertIsArray($payload);
        $this->assertSame('Första flödet', $payload['flows'][0]['title']);
        $this->assertCount(2, $payload['flows'][0]['steps']);
        $this->assertSame('reda', $payload['flows'][0]['steps'][1]['with']);
    }

    public function test_unused_systems_are_not_listed_and_unknown_references_are_dropped(): void
    {
        $entry = $this->ecosystem('b', [
            'flows' => [
                ['id' => 'a', 'type' => 'flow', 'enabled' => true, 'title' => 'Flöde', 'steps' => [
                    ['system' => 'reda', 'with' => 'finns-inte', 'title' => 'Ett'],
                    ['system' => 'finns-inte', 'title' => 'Hoppas över'],
                    ['system' => 'hino', 'with' => 'hino', 'title' => 'Två'],
                ]],
                ['id' => 'b', 'type' => 'flow', 'enabled' => true, 'title' => 'Tomt', 'steps' => []],
                ['id' => 'c', 'type' => 'flow', 'enabled' => false, 'title' => 'Avstängt', 'steps' => [['system' => 'reda', 'title' => 'X']]],
            ],
        ]);

        $payload = app(EcosystemCatalog::class)->payload($entry);

        $this->assertCount(1, $payload['flows']);
        $steps = $payload['flows'][0]['steps'];
        $this->assertSame(['Ett', 'Två'], array_column($steps, 'title'));
        $this->assertNull($steps[0]['with']);
        $this->assertNull($steps[1]['with'], 'Ett system pratar inte med sig självt.');

        $used = array_column(array_filter($payload['systems'], fn ($s) => $s['used']), 'key');
        $this->assertSame(['reda', 'hino'], $used);

        $this->get($entry->url())
            ->assertOk()
            ->assertSee('eco-system-name">Reda', false)
            ->assertDontSee('eco-system-name">Oanvänt', false);
    }

    public function test_json_cannot_break_out_of_the_script_element(): void
    {
        $entry = $this->ecosystem('c', ['headline' => 'x', 'doc_title' => '</script><script>alert(1)</script>']);

        $content = $this->get($entry->url())->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $content);
    }

    public function test_systems_link_to_their_integration_page(): void
    {
        $integration = $this->integration('reda-test');

        $entry = $this->ecosystem('d', [
            'systems' => [
                ['key' => 'reda', 'name' => 'Reda', 'tone' => 'blue', 'integration' => [$integration->id()]],
                ['key' => 'hino', 'name' => 'Hino', 'tone' => 'yellow'],
            ],
        ]);

        $systems = app(EcosystemCatalog::class)->payload($entry)['systems'];

        $this->assertSame($integration->url(), $systems[0]['href']);
        $this->assertNull($systems[1]['href']);

        $this->get($entry->url())->assertOk()->assertSee('Läs mer om Reda', false);
    }

    public function test_index_lists_published_ecosystems_only(): void
    {
        $this->ecosystem('synlig', ['title' => 'Synligt ekosystem']);
        $this->ecosystem('dold', ['title' => 'Dolt ekosystem'], published: false);

        $this->get('/integrationer/ekosystem')
            ->assertOk()
            ->assertSee('Synligt ekosystem', false)
            ->assertDontSee('Dolt ekosystem', false);
    }

    public function test_unpublished_ecosystem_is_not_found(): void
    {
        $entry = $this->ecosystem('utkast', [], published: false);

        $this->get('/integrationer/ekosystem/'.$entry->slug())->assertNotFound();
    }

    public function test_integration_page_links_to_ecosystems_it_is_part_of(): void
    {
        $member = $this->integration('medlem');
        $other = $this->integration('utanfor');

        $this->ecosystem('e', [
            'title' => 'Medlemmarnas ekosystem',
            'systems' => [
                ['key' => 'reda', 'name' => 'Reda', 'tone' => 'blue', 'integration' => [$member->id()]],
                ['key' => 'hino', 'name' => 'Hino', 'tone' => 'yellow'],
            ],
        ]);

        $this->get($member->url())->assertOk()->assertSee('Medlemmarnas ekosystem', false);
        $this->get($other->url())->assertOk()->assertDontSee('Medlemmarnas ekosystem', false);
    }

    public function test_integrations_overview_links_to_the_first_ecosystem(): void
    {
        $this->ecosystem('f', ['title' => 'Första ekosystemet', 'order' => 0]);

        $this->get('/integrationer')->assertOk()->assertSee('Se hur integrationerna jobbar ihop', false);
    }

    public function test_script_is_loaded_for_the_ecosystem_page_and_handles_every_view(): void
    {
        $source = file_get_contents(base_path('resources/js/ekosystem.js'));
        $site = file_get_contents(base_path('resources/js/site.js'));

        $this->assertStringContainsString("import('./ekosystem.js')", $site);

        foreach (['diagram', 'timeline', 'invoice'] as $view) {
            $this->assertStringContainsString("['{$view}',", $source);
        }
    }
}
