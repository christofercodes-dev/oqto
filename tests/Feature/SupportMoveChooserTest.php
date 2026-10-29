<?php

namespace Tests\Feature;

use App\Services\Support\MoveGuides;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportMoveChooserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.intercom.token' => 'test-token',
            'services.intercom.client_secret' => 'test-secret',
            'services.intercom.base_url' => 'https://api.intercom.io',
            'services.intercom.locale' => 'sv',
            'services.intercom.collection_ids' => [],
        ]);

        Cache::flush();
    }

    /**
     * @param  array<int, string>  $titles
     * @return array{sections: array<int, array{title: null, articles: array<int, array<string, string>>}>}
     */
    protected function category(array $titles): array
    {
        return ['sections' => [[
            'title' => null,
            'articles' => array_map(fn (string $title) => ['title' => $title, 'url' => '/x/'.md5($title), 'description' => ''], $titles),
        ]]];
    }

    public function test_guides_are_sorted_by_system_and_method(): void
    {
        $move = MoveGuides::build($this->category([
            'Importguiden – Flytta till Oqto från Spiris',
            'Exportera filer från Fortnox',
            'Flytta till Oqto från Fortnox via integration',
            'Felsökning – importen från Fortnox',
            'Importguiden – Flytta till Oqto från Fortnox',
            'Flytta till Oqto från Spiris via integration',
        ]));

        $this->assertSame(['Fortnox', 'Spiris'], array_column($move['systems'], 'name'));
        $this->assertSame('system', $move['initial']);

        [$fortnox, $spiris] = $move['systems'];

        $this->assertSame(['integration', 'manual'], array_column($fortnox['methods'], 'key'));
        $this->assertSame('fortnox', $fortnox['go']);
        $this->assertSame(4, $fortnox['guides']);

        $manual = $fortnox['methods'][1];

        // Exportera först, sedan importen, och felsökningen i en egen sektion.
        $this->assertSame(
            ['Exportera filer från Fortnox', 'Importguiden – Flytta till Oqto från Fortnox'],
            array_column($manual['sections'][0]['articles'], 'title')
        );
        $this->assertSame('Om något går fel', $manual['sections'][1]['title']);
        $this->assertSame('fortnox-manual', $manual['id']);
        $this->assertSame('fortnox', $manual['back']);

        // Spiris saknar felsökning här men har båda sätten.
        $this->assertCount(1, $spiris['methods'][0]['sections']);
    }

    public function test_a_system_with_one_way_skips_the_method_step(): void
    {
        $move = MoveGuides::build($this->category([
            'Exportera filer från Fortnox',
            'Flytta till Oqto från Fortnox via integration',
            'Importguiden – Flytta till Oqto från Visma',
        ]));

        $visma = collect($move['systems'])->firstWhere('name', 'Visma');

        $this->assertTrue($visma['single']);
        $this->assertSame('visma-manual', $visma['go']);
        $this->assertSame('system', $visma['methods'][0]['back']);
    }

    public function test_only_one_system_starts_at_its_own_step(): void
    {
        $move = MoveGuides::build($this->category([
            'Exportera filer från Fortnox',
            'Flytta till Oqto från Fortnox via integration',
        ]));

        $this->assertSame('fortnox', $move['initial']);
    }

    public function test_articles_without_a_system_end_up_last_and_no_systems_means_no_chooser(): void
    {
        $move = MoveGuides::build($this->category([
            'Allmänt om flytt',
            'Exportera filer från Fortnox',
        ]));

        $this->assertSame(['Fortnox', MoveGuides::OTHER_SYSTEM], array_column($move['systems'], 'name'));

        $this->assertNull(MoveGuides::build($this->category(['Allmänt om flytt', 'Tips för bokföring'])));
        $this->assertNull(MoveGuides::build(['sections' => []]));
    }

    public function test_the_move_category_page_shows_the_chooser(): void
    {
        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    ['id' => '1', 'name' => 'Flytta till Oqto', 'description' => 'Guide', 'parent_id' => null, 'order' => 1],
                    ['id' => '2', 'name' => 'Fakturering', 'parent_id' => null, 'order' => 2],
                ],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    ['id' => '10', 'title' => 'Exportera filer från Fortnox', 'body' => '<p>a</p>', 'state' => 'published', 'parent_id' => '1'],
                    ['id' => '11', 'title' => 'Flytta till Oqto från Fortnox via integration', 'body' => '<p>b</p>', 'state' => 'published', 'parent_id' => '1'],
                    ['id' => '12', 'title' => 'Exportera filer från Spiris', 'body' => '<p>c</p>', 'state' => 'published', 'parent_id' => '1'],
                    ['id' => '13', 'title' => 'Skapa en faktura', 'body' => '<p>d</p>', 'state' => 'published', 'parent_id' => '2'],
                ],
            ]),
        ]);

        $this->get('/support/flytta-till-oqto')
            ->assertOk()
            ->assertSee('Vilket ekonomisystem flyttar du från?', false)
            ->assertSee('data-move-go="fortnox"', false)
            ->assertSee('data-move-go="spiris-manual"', false)
            ->assertSee('data-move-view="fortnox-integration"', false)
            ->assertSee('Hur vill du flytta från Fortnox?', false)
            // Spiris har bara ett sätt, så det hoppar över steg 2.
            ->assertDontSee('Hur vill du flytta från Spiris?', false);

        // Övriga kategorier listar fortfarande artiklarna som förut.
        $this->get('/support/fakturering')->assertOk()->assertSee('Skapa en faktura')->assertDontSee('data-move', false);
    }

    public function test_what_follows_is_taken_word_for_word_from_the_guides(): void
    {
        $integration = '<h1><b>Vad följer med från X?</b></h1>'
            .'<details><summary><h2><b>Det här följer med</b></h2></summary><div><ul>'
            .'<li><p class="no-margin"><b>Bokföring:</b> kontoplan och verifikat.</p></li>'
            .'<li><p class="no-margin">Kunder</p></li></ul></div></details>'
            .'<details><summary><h2><b>Det här följer inte med</b></h2></summary><div><ul>'
            .'<li><p class="no-margin">Bankkonton</p></li></ul>'
            .'<p class="no-margin">Dessa behöver du sätta upp på nytt.</p>'
            .'<div class="intercom-interblocks-callout"><p>👉 Tips som inte ska med</p></div></div></details>'
            .'<h1>Bra att veta</h1><ul><li>Ska inte med</li></ul>';

        $scope = MoveGuides::scopeOf($integration);

        $this->assertSame('Det här följer med', $scope['follows_title']);
        $this->assertSame(['<strong>Bokföring:</strong> kontoplan och verifikat.', 'Kunder'], $scope['follows']);
        $this->assertSame('Det här följer inte med', $scope['skips_title']);
        $this->assertSame(['Bankkonton'], $scope['skips']);
        $this->assertSame(['Dessa behöver du sätta upp på nytt.'], $scope['notes']);

        // Importguiden: en rubrik med lista och stycke.
        $guide = '<h1><b>Vad följer med via Importguiden?</b></h1><ul><li><p><b>Bokföring</b> via SIE.</p></li></ul>'
            .'<p>Följande följer <b>inte</b> med: avtal.</p><h1>Efter importen</h1><p>Annat</p>';

        $scope = MoveGuides::scopeOf($guide);

        $this->assertSame('Vad följer med via Importguiden?', $scope['title']);
        $this->assertSame(['<strong>Bokföring</strong> via SIE.'], $scope['follows']);
        $this->assertSame(['Följande följer <strong>inte</strong> med: avtal.'], $scope['notes']);

        $this->assertNull(MoveGuides::scopeOf('<h1>Något annat</h1><p>Text</p>'));
    }

    public function test_the_guide_step_shows_what_follows_from_the_matching_guide(): void
    {
        $body = '<h2>Det här följer med</h2><ul><li><p>Unik punkt ett</p></li></ul>'
            .'<h2>Det här följer inte med</h2><ul><li><p>Unik punkt två</p></li></ul>';

        $move = MoveGuides::build(['sections' => [[
            'title' => null,
            'articles' => [
                ['title' => 'Flytta till Oqto från Fortnox via integration', 'url' => '/s/a', 'body' => $body],
                ['title' => 'Exportera filer från Fortnox', 'url' => '/s/b', 'body' => '<p>Exportera</p>'],
            ],
        ]]]);

        [$integration, $manual] = $move['systems'][0]['methods'];

        $this->assertSame(['Unik punkt ett'], $integration['scope']['follows']);
        $this->assertSame('/s/a', $integration['scope']['url']);
        // Manuellt saknar en guide med uppgiften och får ingen kolumn.
        $this->assertNull($manual['scope']);
    }
}
