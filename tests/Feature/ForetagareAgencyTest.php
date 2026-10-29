<?php

namespace Tests\Feature;

use App\Services\Foretagare\FeatureCatalog;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ForetagareAgencyTest extends TestCase
{
    protected const PREFIX = 'zz-test-';

    /**
     * @var array<int, \Statamic\Contracts\Entries\Entry>
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
    protected function feature(string $slug, array $data = [], bool $published = true): \Statamic\Contracts\Entries\Entry
    {
        $entry = Entry::make()
            ->collection(FeatureCatalog::FEATURES)
            ->slug(self::PREFIX.$slug)
            ->published($published)
            ->data(array_merge([
                'title' => ucfirst($slug),
                'short_description' => 'Kort om '.$slug,
                'benefits' => [['title' => 'Fördel för '.$slug, 'text' => 'Förklaring av '.$slug]],
            ], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function agency(string $slug, array $data = [], bool $published = true): \Statamic\Contracts\Entries\Entry
    {
        $entry = Entry::make()
            ->collection(FeatureCatalog::AGENCIES)
            ->slug(self::PREFIX.$slug)
            ->published($published)
            ->data(array_merge(['title' => 'Byrå '.$slug, 'brand_color' => '#123456'], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    public function test_for_foretagare_lists_published_features_in_order(): void
    {
        $this->feature('bbb', ['title' => 'Bokföring', 'order' => 2]);
        $this->feature('aaa', ['title' => 'Fakturering', 'order' => 1]);
        $this->feature('ccc', ['title' => 'Dold funktion'], published: false);

        $response = $this->get('/for-foretagare')->assertOk();

        $response->assertSee('Fakturering')->assertSee('Bokföring')->assertDontSee('Dold funktion');
        $response->assertSeeInOrder(['Fakturering', 'Bokföring']);
        $response->assertSee('href="/for-foretagare/'.self::PREFIX.'aaa"', false);
        $response->assertDontSee('is-branded', false);
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $response->assertDontSee('name="robots"', false);
    }

    public function test_a_feature_page_shows_its_content_and_unpublished_ones_are_gone(): void
    {
        $this->feature('a', ['title' => 'Fakturering', 'short_description' => 'Skicka fakturor']);
        $this->feature('b', ['title' => 'Dold'], published: false);

        $this->get('/for-foretagare/'.self::PREFIX.'a')
            ->assertOk()
            ->assertSee('Fakturering')
            ->assertSee('Skicka fakturor')
            ->assertSee('Vad den gör')
            ->assertSee('Fördel för a')
            ->assertSee('Boka demo');

        $this->get('/for-foretagare/'.self::PREFIX.'b')->assertNotFound();
    }

    public function test_an_agency_lists_only_its_selected_features_in_its_order(): void
    {
        $a = $this->feature('a', ['title' => 'Fakturering', 'order' => 1]);
        $b = $this->feature('b', ['title' => 'Bokföring', 'order' => 2]);
        $this->feature('c', ['title' => 'Lön', 'order' => 3]);

        $this->agency('winwin', ['title' => 'Win Win', 'features' => [$b->id(), $a->id()]]);

        $response = $this->get('/byra/'.self::PREFIX.'winwin')->assertOk();

        $response->assertSee('Win Win')
            ->assertDontSee('Lön')
            ->assertSeeInOrder(['Bokföring', 'Fakturering'])
            ->assertSee('Funktioner för dig som är kund hos Win Win')
            ->assertSee('href="/byra/'.self::PREFIX.'winwin/'.self::PREFIX.'a"', false);
    }

    public function test_an_agency_without_a_selection_shows_every_feature(): void
    {
        $this->feature('a', ['title' => 'Fakturering']);
        $this->feature('b', ['title' => 'Bokföring']);
        $this->feature('c', ['title' => 'Dold'], published: false);

        $this->agency('alla');

        $this->get('/byra/'.self::PREFIX.'alla')
            ->assertOk()
            ->assertSee('Fakturering')
            ->assertSee('Bokföring')
            ->assertDontSee('Dold');
    }

    public function test_interactive_demos_are_shown_on_the_feature_page_and_under_agencies(): void
    {
        $feature = $this->feature('demo', [
            'title' => 'Fakturering',
            'demos' => [
                ['type' => 'invoice-desktop', 'title' => 'Skapa faktura på dator', 'text' => 'Fyra steg', 'next_text' => 'Klicka här för att se påminnelserna'],
                ['type' => 'reminders', 'title' => 'Skicka påminnelser', 'text' => ''],
            ],
        ]);
        $this->feature('utan', ['title' => 'Bokföring']);

        $this->agency('winwin', ['features' => [$feature->id()]]);

        foreach (['/for-foretagare/'.self::PREFIX.'demo', '/byra/'.self::PREFIX.'winwin/'.self::PREFIX.'demo'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Prova själv')
                ->assertSee('data-demo="invoice-desktop"', false)
                ->assertSee('data-demo="reminders"', false)
                ->assertDontSee('data-demo="invoice-mobile"', false)
                ->assertSeeInOrder(['Skapa faktura på dator', 'Skicka påminnelser'])
                // Guiden: en stegindikator, ett steg i taget och knappen till nästa steg.
                ->assertSee('data-demo-wizard', false)
                ->assertSee('data-wiz-full', false)
                ->assertSee('Helskärm')
                ->assertSee('Steg 1 av 2')
                ->assertSee('Steg 2 av 2')
                ->assertSee('data-wiz-go="2"', false)
                ->assertSee('Klicka här för att se påminnelserna')
                ->assertSee('Börja om från steg 1');
        }

        $this->get('/for-foretagare/'.self::PREFIX.'utan')
            ->assertOk()
            ->assertDontSee('Prova själv')
            ->assertDontSee('data-demo-wizard', false)
            ->assertDontSee('data-demo=', false);
    }

    public function test_every_demo_type_in_the_blueprint_has_a_script_module(): void
    {
        $options = array_keys(\Statamic\Facades\Collection::find(FeatureCatalog::FEATURES)
            ->entryBlueprint('funktion')
            ->field('demos')
            ->fieldtype()
            ->fields()
            ->get('type')
            ->get('options'));

        $this->assertEqualsCanonicalizing(['invoice-desktop', 'invoice-mobile', 'reminders'], $options);

        $loader = file_get_contents(resource_path('js/demos/index.js'));

        $this->assertFileExists(resource_path('js/demos/wizard.js'));
        $this->assertStringContainsString("import('./wizard.js')", $loader);

        foreach ($options as $type) {
            $this->assertStringContainsString("import('./{$type}.js')", $loader, "Demot \"{$type}\" saknas i resources/js/demos/index.js");
            $this->assertFileExists(resource_path("js/demos/{$type}.js"));
        }
    }

    public function test_the_agency_feature_page_shows_the_same_content_with_the_agencys_branding(): void
    {
        $feature = $this->feature('a', [
            'title' => 'Fakturering',
            'short_description' => 'Skicka fakturor',
        ]);

        $this->agency('winwin', [
            'title' => 'Win Win',
            'features' => [$feature->id()],
            'brand_color' => '#123456',
            'contact_url' => 'https://winwin.example/kontakt',
            'cta_label' => 'Prata med oss',
        ]);

        $original = $this->get('/for-foretagare/'.self::PREFIX.'a')->assertOk();
        $branded = $this->get('/byra/'.self::PREFIX.'winwin/'.self::PREFIX.'a')->assertOk();

        // Samma innehåll...
        foreach (['Fakturering', 'Skicka fakturor', 'Vad den gör', 'Fördel för a', 'Förklaring av a'] as $text) {
            $original->assertSee($text);
            $branded->assertSee($text);
        }

        // ...men med byråns profil.
        $branded->assertSee('is-branded', false)
            ->assertSee('--brand: #123456; --brand-ink: #ffffff;', false)
            ->assertSee('Win Win')
            ->assertSee('Prata med oss')
            ->assertSee('href="https://winwin.example/kontakt"', false)
            ->assertSee('href="/byra/'.self::PREFIX.'winwin"', false);

        // Och originalet påverkas inte.
        $original->assertDontSee('--brand: #123456', false)->assertDontSee('Prata med oss');
    }

    public function test_a_feature_the_agency_has_not_chosen_is_not_available_under_the_agency(): void
    {
        $chosen = $this->feature('a');
        $this->feature('b');

        $this->agency('winwin', ['features' => [$chosen->id()]]);

        $this->get('/byra/'.self::PREFIX.'winwin/'.self::PREFIX.'a')->assertOk();
        $this->get('/byra/'.self::PREFIX.'winwin/'.self::PREFIX.'b')->assertNotFound();
        // ...men den finns kvar under För företagare.
        $this->get('/for-foretagare/'.self::PREFIX.'b')->assertOk();
    }

    public function test_unpublished_and_unknown_agencies_are_not_found(): void
    {
        $this->feature('a');
        $this->agency('dold', published: false);

        $this->get('/byra/'.self::PREFIX.'dold')->assertNotFound();
        $this->get('/byra/'.self::PREFIX.'dold/'.self::PREFIX.'a')->assertNotFound();
        $this->get('/byra/finns-inte')->assertNotFound();
        $this->get('/byra/finns-inte/fakturering')->assertNotFound();
    }

    public function test_everything_under_byra_is_hidden_from_search_engines(): void
    {
        $feature = $this->feature('a');
        $this->agency('winwin', ['features' => [$feature->id()]]);

        $urls = [
            '/byra/'.self::PREFIX.'winwin',
            '/byra/'.self::PREFIX.'winwin/'.self::PREFIX.'a',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url)->assertOk();

            $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $response->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);
        }

        // Även sidor som inte finns under /byra får headern.
        $this->get('/byra/finns-inte')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get('/byra/finns-inte/nagot')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // Och byråerna listas inte i robots.txt (som är publik).
        $robots = public_path('robots.txt');

        if (is_file($robots)) {
            $this->assertStringNotContainsString('byra', file_get_contents($robots));
        }
    }

    public function test_the_cta_falls_back_to_email(): void
    {
        $this->feature('a');

        $this->agency('mejl', ['contact_email' => 'hej@byran.example']);
        $this->agency('utan');

        $this->get('/byra/'.self::PREFIX.'mejl')
            ->assertSee('href="mailto:hej@byran.example"', false)
            ->assertSee('Kontakta oss');

        $this->get('/byra/'.self::PREFIX.'utan')->assertDontSee('Kontakta oss');
    }

    public function test_agency_pages_keep_the_regular_oqto_navigation_and_footer(): void
    {
        $feature = $this->feature('a');
        $this->agency('winwin', ['title' => 'Win Win', 'features' => [$feature->id()]]);

        foreach (['/byra/'.self::PREFIX.'winwin', '/byra/'.self::PREFIX.'winwin/'.self::PREFIX.'a'] as $url) {
            $response = $this->get($url)->assertOk();

            // Oqtos meny, logga och sidfot är orörda...
            $response->assertSee('navbar-wrapper', false)
                ->assertSee('/images/logos/logga02.svg', false)
                ->assertSee('site-footer', false)
                ->assertSee('app.oqto.se', false);

            // ...och byråns profil ligger bara i sidans innehåll.
            $response->assertSee('foretagare-agency-strip', false)
                ->assertSee('Din byrå')
                ->assertSee('Win Win')
                ->assertDontSee('foretagare-agency-bar', false)
                ->assertDontSee('Drivs av');
        }

        // Fliktiteln nämner byrån men också Oqto.
        $this->get('/byra/'.self::PREFIX.'winwin')->assertSee('– Win Win · Oqto', false);

        // Oqtos egna sidor har inget byråfält.
        $this->get('/for-foretagare/'.self::PREFIX.'a')->assertDontSee('foretagare-agency-strip', false);
    }

    public function test_brand_colors_get_a_readable_text_color_and_bad_values_are_replaced(): void
    {
        $catalog = app(FeatureCatalog::class);

        $this->assertSame('--brand: #ffe14d; --brand-ink: #1a1715;', $catalog->brandStyle('#ffe14d'));
        $this->assertSame('--brand: #112244; --brand-ink: #ffffff;', $catalog->brandStyle('#124'));
        $this->assertSame('--brand: #ff6319; --brand-ink: #1a1715;', $catalog->brandStyle(null));

        // Allt som inte är en hexfärg ersätts, så inget annat hamnar i style-attributet.
        foreach (['red; background:url(x)', '#12345', '"><script>', 'expression(1)', ['#fff']] as $bad) {
            $this->assertSame($catalog->brandStyle(null), $catalog->brandStyle($bad));
        }
    }
}
