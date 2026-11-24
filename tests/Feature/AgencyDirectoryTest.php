<?php

namespace Tests\Feature;

use App\Services\Byrakartan\AgencyDirectory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgencyDirectoryTest extends TestCase
{
    protected const API = 'byrakartan.se/api/v1/byraer*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.byrakartan.link_profiles' => false]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function row(string $slug, string $name, string $city, array $overrides = []): array
    {
        return array_merge([
            'slug' => $slug,
            'namn' => $name,
            'beskrivning' => 'Personlig redovisning.',
            'ort' => $city,
            'webbplats' => 'https://'.$slug.'.example.se',
            'profil_url' => 'https://byrakartan.se/byraer/'.$slug,
            'betyg' => 4.8,
            'antal_betyg' => 5,
            'verifierad' => true,
            'auktoriserad' => true,
            'digital' => false,
            'tjanster' => [
                ['slug' => 'revision', 'namn' => 'Revision'],
                ['slug' => 'lonehantering', 'namn' => 'Lönehantering'],
            ],
            'system' => [['slug' => 'oqto', 'namn' => 'Oqto']],
            'lat' => 59.33,
            'lng' => 15.21,
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function fakeApi(array $rows): void
    {
        Http::fake([
            self::API => Http::response([
                'data' => $rows,
                'meta' => ['total' => count($rows), 'sida' => 1, 'sidor' => 1, 'per_sida' => 100],
            ]),
        ]);
    }

    /** Http::fake() lägger bara till svar (det första som matchar vinner), så en ny uppsättning kräver en ny fabrik. */
    protected function replaceFake(array $responses): void
    {
        Http::swap(new Factory);
        Http::fake($responses);
    }

    protected function fakeThreeAgencies(): void
    {
        $this->fakeApi([
            $this->row('kvarter-orebro', 'Kvarter Byrå Örebro', 'Örebro', ['lat' => 59.27, 'lng' => 15.21, 'auktoriserad' => true]),
            $this->row('fokus-stockholm', 'Fokus Revision & Redovisning', 'Stockholm', [
                'betyg' => 4.2,
                'antal_betyg' => 11,
                'lat' => 59.33,
                'lng' => 18.07,
                'digital' => true,
                'auktoriserad' => true,
                'tjanster' => [['slug' => 'deklaration', 'namn' => 'Deklaration']],
            ]),
            $this->row('vass-umea', 'Vass Byrå Umeå', 'Umeå', ['betyg' => null, 'antal_betyg' => 0, 'lat' => 63.83, 'lng' => 20.26, 'auktoriserad' => false, 'digital' => true, 'tjanster' => [['slug' => 'revision', 'namn' => 'Revision']]]),
        ]);
    }

    public function test_it_lists_the_agencies_with_city_rating_services_and_website(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('Byråer som använder Oqto')
            ->assertSee('Kvarter Byrå Örebro')
            ->assertSee('Fokus Revision &amp; Redovisning', false)
            ->assertSee('Stockholm')
            ->assertSee('4,8')
            ->assertSee('(11)')
            ->assertSee('Lönehantering')
            ->assertSee('Deklaration');
    }

    public function test_an_unknown_agency_address_leads_to_the_list(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan/finns-inte')->assertRedirect('/byrakartan');
        $this->get('/byrakartan/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_cards_link_to_each_agencys_own_page_and_never_show_the_web_address(): void
    {
        $this->fakeThreeAgencies();

        $response = $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('href="/byrakartan/kvarter-orebro"', false)
            ->assertSee('href="/byrakartan/fokus-stockholm"', false)
            ->assertSee('Visa mer')
            // Ingen popup: klicket går till byråns sida.
            ->assertDontSee('<dialog', false)
            ->assertDontSee('data-agency-open', false)
            ->assertDontSee('href="https://kvarter-orebro.example.se"', false);

        // Webbadressen finns varken som text eller i listans data.
        $this->assertStringNotContainsString('kvarter-orebro.example.se', $response->getContent());
        $this->assertArrayNotHasKey('website', $this->agenciesJson($response->getContent())[0]);
        $this->assertSame('/byrakartan/kvarter-orebro', $this->agenciesJson($response->getContent())[0]['url']);
    }

    public function test_every_agency_has_its_own_page(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan/fokus-stockholm')
            ->assertOk()
            ->assertSee('<title>Fokus Revision &amp; Redovisning – byrå som använder Oqto', false)
            ->assertSee('Fokus Revision &amp; Redovisning i Stockholm är en redovisningsbyrå', false)
            ->assertSee('<h1 class="articles-hero-title">Fokus Revision &amp; Redovisning</h1>', false)
            ->assertSee('Stockholm')
            ->assertSee('★ 4,2 (11 omdömen)')
            ->assertSee('Auktoriserad')
            ->assertSee('100 % digital')
            ->assertSee('Deklaration')
            ->assertSee('href="/byrakartan"', false)
            ->assertSee('Alla byråer')
            ->assertSee('href="https://fokus-stockholm.example.se"', false)
            ->assertSee('data-track="webbplats"', false)
            ->assertDontSee('<dialog', false);
    }

    public function test_the_agency_page_shows_everything_the_agency_has_and_nothing_secret(): void
    {
        config(['services.mapbox.token' => 'pk.public_token']);

        $this->fakeApi([$this->row('full', 'Full Byrå', 'Lund', [
            'beskrivning' => 'Siffror med själ.',
            'om' => "Första stycket.\n\n\n\nAndra stycket <b>fet</b>.",
            'logotyp' => 'https://cdn.example.se/logo.png',
            'bilder' => ['https://cdn.example.se/1.jpg', 'javascript:alert(1)', 'https://cdn.example.se/2.jpg'],
            'gatuadress' => 'Storgatan 1',
            'postnummer' => '222 22',
            'har_telefon' => true,
            'har_epost' => true,
            // Skulle API:et mot förmodan skicka med själva uppgifterna får de aldrig hamna i sidan.
            'telefon' => '046-123 45 67',
            'epost' => 'hemlig@byra.se',
        ])]);

        $this->get('/byrakartan/full')
            ->assertOk()
            ->assertSee('src="https://cdn.example.se/logo.png"', false)
            ->assertSee('alt="Logotyp för Full Byrå"', false)
            ->assertSee('Siffror med själ.')
            ->assertSee('Om byrån')
            ->assertSeeInOrder(['<p>Första stycket.</p>', '<p>Andra stycket fet.</p>'], false)
            ->assertSee('src="https://cdn.example.se/1.jpg"', false)
            ->assertSee('src="https://cdn.example.se/2.jpg"', false)
            ->assertDontSee('javascript:', false)
            ->assertSee('id="byra-contact-title"', false)
            ->assertSee('Storgatan 1')
            ->assertSee('222 22 Lund')
            ->assertSee('data-agency-map', false)
            ->assertSee('data-mapbox-token="pk.public_token"', false)
            // Telefon och e-post bakom knappar: räknas först när besökaren klickar.
            ->assertSee('Visa telefonnummer')
            ->assertSee('Visa e-post')
            ->assertSee('data-reveal="telefon"', false)
            ->assertSee('data-reveal="epost"', false)
            ->assertSee('data-api="https://byrakartan.se/api/v1"', false)
            ->assertSee('data-slug="full"', false)
            ->assertSee('property="og:image" content="https://cdn.example.se/1.jpg"', false)
            ->assertDontSee('046-123 45 67')
            ->assertDontSee('hemlig@byra.se');
    }

    public function test_the_postcode_is_written_the_swedish_way_and_a_city_alone_is_not_an_address(): void
    {
        $this->fakeApi([
            $this->row('a', 'Byrå A', 'Norrköping', ['gatuadress' => 'Ståthögavägen 48', 'postnummer' => '60223']),
            $this->row('b', 'Byrå B', 'Lund', ['gatuadress' => null, 'postnummer' => null]),
        ]);

        $this->get('/byrakartan/a')->assertOk()->assertSee('602 23 Norrköping');
        $this->get('/byrakartan/b')->assertOk()->assertDontSee('byra-contact-address', false);
    }

    public function test_contact_card_and_map_pair_up_when_there_is_no_longer_text(): void
    {
        $this->fakeApi([
            $this->row('kort', 'Kort Byrå', 'Lund', ['tjanster' => [], 'gatuadress' => 'Storgatan 1', 'postnummer' => '222 22']),
            $this->row('lang', 'Lång Byrå', 'Malmö', ['om' => 'En längre text om byrån.']),
        ]);

        $this->get('/byrakartan/kort')->assertOk()->assertSee('byra-detail-grid"', false)->assertDontSee('has-main', false);
        $this->get('/byrakartan/lang')->assertOk()->assertSee('has-main', false)->assertSee('En längre text om byrån.');
    }

    public function test_what_an_agency_does_not_have_is_left_out_of_its_page(): void
    {
        $this->fakeApi([$this->row('tom', 'Tom Byrå', 'Lund', ['webbplats' => null, 'tjanster' => [], 'betyg' => null, 'lat' => null, 'lng' => null, 'auktoriserad' => false, 'digital' => false])]);

        $this->get('/byrakartan/tom')
            ->assertOk()
            ->assertSee('Tom Byrå')
            ->assertDontSee('byra-detail-logo', false)
            ->assertDontSee('Om byrån')
            ->assertDontSee('Tjänster')
            ->assertDontSee('byra-contact', false)
            ->assertDontSee('Besök webbplats')
            ->assertDontSee('data-reveal', false)
            ->assertDontSee('data-agency-map', false)
            ->assertDontSee('byra-detail-gallery', false);
    }

    public function test_phone_and_email_buttons_need_click_tracking(): void
    {
        $this->fakeApi([$this->row('a', 'Byrå A', 'Lund', ['har_telefon' => true, 'har_epost' => true])]);

        $this->get('/byrakartan/a')->assertOk()->assertSee('data-reveal="telefon"', false);

        // Utan räkning finns inget att hämta uppgiften från, så knapparna visas inte alls.
        config(['services.byrakartan.track_clicks' => false]);

        $this->get('/byrakartan/a')
            ->assertOk()
            ->assertDontSee('data-reveal', false)
            ->assertSee('data-api=""', false);
    }

    public function test_an_agency_without_phone_or_email_gets_no_buttons(): void
    {
        $this->fakeApi([$this->row('a', 'Byrå A', 'Lund')]);

        $this->get('/byrakartan/a')->assertOk()->assertDontSee('data-reveal', false);
    }

    public function test_the_site_of_byrakartan_is_never_linked_while_it_is_down(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan')->assertOk()->assertDontSee('href="https://byrakartan', false);
        $this->get('/byrakartan/kvarter-orebro')
            ->assertOk()
            ->assertDontSee('href="https://byrakartan', false)
            ->assertDontSee('Se profilen på Byråkartan');
    }

    public function test_profile_links_can_be_switched_on_with_the_flag(): void
    {
        config(['services.byrakartan.link_profiles' => true]);
        $this->fakeThreeAgencies();

        $this->get('/byrakartan')->assertOk()->assertSee('href="https://byrakartan.se"', false);

        $this->get('/byrakartan/kvarter-orebro')
            ->assertOk()
            ->assertSee('href="https://byrakartan.se/byraer/kvarter-orebro"', false)
            ->assertSee('Se profilen på Byråkartan');
    }

    public function test_markup_in_an_agency_name_cannot_break_the_agency_page(): void
    {
        $this->fakeApi([$this->row('ond', 'Ond </script><script>alert(1)</script> Byrå', 'Lund')]);

        $this->get('/byrakartan/ond')->assertOk()->assertDontSee('<script>alert(1)', false);
    }

    public function test_the_agency_page_does_not_depend_on_any_filter(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan/vass-umea?digital=1&q=Örebro&stad=x')->assertOk()->assertSee('Vass Byrå Umeå');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function agenciesJson(string $html): array
    {
        preg_match('~id="byraer-data">(.*?)</script>~s', $html, $m);

        return json_decode($m[1], true);
    }

    public function test_it_asks_the_api_for_the_oqto_agencies_without_credentials(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan')->assertOk();

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://byrakartan.se/api/v1/byraer')
                && $request['system'] === 'oqto'
                && (int) $request['limit'] === 100
                && ! $request->hasHeader('Authorization')
                && ! $request->hasHeader('Cookie');
        });
    }

    public function test_agencies_without_a_rating_show_no_rating(): void
    {
        $this->fakeApi([$this->row('vass-umea', 'Vass Byrå Umeå', 'Umeå', ['betyg' => null, 'antal_betyg' => 0])]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('Vass Byrå Umeå')
            ->assertDontSee('byra-card-rating', false)
            ->assertDontSee('★');
    }

    public function test_it_filters_on_agency_type(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?digital=1')
            ->assertOk()
            ->assertSee('Fokus Revision')
            ->assertSee('Vass Byrå Umeå')
            ->assertDontSee('Kvarter Byrå Örebro')
            ->assertSee('Visar 2 av 3 byråer');

        $this->get('/byrakartan?auktoriserad=1')
            ->assertOk()
            ->assertSee('Kvarter Byrå Örebro')
            ->assertSee('Fokus Revision')
            ->assertDontSee('Vass Byrå Umeå');

        $this->get('/byrakartan?digital=1&auktoriserad=1')
            ->assertOk()
            ->assertSee('Fokus Revision')
            ->assertDontSee('Kvarter Byrå Örebro')
            ->assertDontSee('Vass Byrå Umeå');
    }

    public function test_type_filters_are_hidden_while_no_agency_has_the_attribute(): void
    {
        $this->fakeApi([
            $this->row('a', 'Byrå A', 'Lund', ['auktoriserad' => false, 'digital' => false]),
            $this->row('b', 'Byrå B', 'Malmö', ['auktoriserad' => false, 'digital' => false]),
        ]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertDontSee('Auktoriserad (SRF/FAR)')
            ->assertDontSee('100 % digitalt')
            ->assertDontSee('Typ av byrå');

        // Ett gammalt filter i adressen ger inte noll träffar utan att gå att rensa.
        $this->get('/byrakartan?auktoriserad=1&digital=1')
            ->assertOk()
            ->assertSee('Byrå A')
            ->assertSee('Byrå B');
    }

    public function test_each_type_filter_appears_once_an_agency_has_the_attribute(): void
    {
        $this->fakeApi([
            $this->row('a', 'Byrå A', 'Lund', ['auktoriserad' => true, 'digital' => false]),
            $this->row('b', 'Byrå B', 'Malmö', ['auktoriserad' => false, 'digital' => false]),
        ]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('Auktoriserad (SRF/FAR)')
            ->assertDontSee('100 % digitalt');
    }

    public function test_the_agency_must_offer_all_the_chosen_services(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?tjanst[]=revision')
            ->assertOk()
            ->assertSee('Kvarter Byrå Örebro')
            ->assertSee('Vass Byrå Umeå')
            ->assertDontSee('Fokus Revision');

        $this->get('/byrakartan?tjanst[]=revision&tjanst[]=lonehantering')
            ->assertOk()
            ->assertSee('Kvarter Byrå Örebro')
            ->assertDontSee('Vass Byrå Umeå');

        $this->get('/byrakartan?tjanst[]=revision&tjanst[]=deklaration')
            ->assertOk()
            ->assertSee('Ingen byrå matchar sökningen');
    }

    public function test_it_searches_on_city_or_name(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?q=stockholm')->assertOk()->assertSee('Fokus Revision')->assertDontSee('Kvarter Byrå Örebro');
        $this->get('/byrakartan?q=kvarter')->assertOk()->assertSee('Kvarter Byrå Örebro')->assertDontSee('Fokus Revision');
        $this->get('/byrakartan?q=finnsinte')->assertOk()->assertSee('Ingen byrå matchar sökningen');
    }

    public function test_a_search_lands_on_the_results_and_not_at_the_top_of_the_page(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?q=Lund')
            ->assertOk()
            ->assertSee('action="/byrakartan#resultat"', false)
            ->assertSee('id="resultat"', false);
    }

    public function test_the_search_form_keeps_what_was_chosen(): void
    {
        $this->fakeThreeAgencies();

        $html = $this->get('/byrakartan?q=Lund&digital=1&tjanst[]=revision&sort=namn')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="q" value="Lund"/', $html);
        $this->assertMatchesRegularExpression('/name="digital" value="1" checked/', $html);
        $this->assertMatchesRegularExpression('/name="tjanst\[\]" value="revision" checked/', $html);
        $this->assertMatchesRegularExpression('/value="namn" selected/', $html);
        $this->assertStringContainsString('Rensa filter', $html);
    }

    public function test_near_me_sorts_by_distance_and_shows_it_on_the_cards(): void
    {
        $this->fakeThreeAgencies();

        // Strax utanför Örebro: Örebro (ca 3 km) före Stockholm före Umeå.
        $response = $this->get('/byrakartan?nara=59.30,15.20')->assertOk();

        $response->assertSeeInOrder(['Kvarter Byrå Örebro', 'Fokus Revision', 'Vass Byrå Umeå'])
            ->assertSee('närmast din position')
            ->assertSee('value="narmast" selected', false)
            ->assertSee('data-origin="59.3,15.2"', false)
            ->assertSee('byra-card-distance', false);

        $this->assertMatchesRegularExpression('/byra-card-distance">\s*\d,\d km/', $response->getContent());

        // Kartan får avståndet per byrå, så att den kan zooma in på platsen och de närmaste byråerna.
        $kms = array_column($this->agenciesJson($response->getContent()), 'km', 'name');

        $this->assertEqualsWithDelta(3.4, $kms['Kvarter Byrå Örebro'], 0.5);
        $this->assertGreaterThan($kms['Kvarter Byrå Örebro'], $kms['Fokus Revision & Redovisning']);
    }

    public function test_the_position_is_rounded_and_invalid_positions_are_ignored(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?nara=59.30456,15.20987')->assertOk()->assertSee('data-origin="59.3,15.21"', false);

        foreach (['hej', '999,15', '59.3', "59.3,15.2'<script>", '59.3;15.2'] as $bad) {
            $this->get('/byrakartan?nara='.urlencode($bad))
                ->assertOk()
                ->assertDontSee('närmast din position')
                ->assertDontSee('15.2\'<script>', false)
                ->assertSee('data-origin=""', false);
        }
    }

    public function test_sorting_on_rating_and_name(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?sort=betyg')->assertOk()->assertSeeInOrder(['Kvarter Byrå Örebro', 'Fokus Revision', 'Vass Byrå Umeå']);
        $this->get('/byrakartan?sort=namn')->assertOk()->assertSeeInOrder(['Fokus Revision', 'Kvarter Byrå Örebro', 'Vass Byrå Umeå']);

        // "Närmast" kräver en plats; utan den gäller den vanliga ordningen.
        $this->get('/byrakartan?sort=narmast')->assertOk()->assertDontSee('value="narmast"', false);
    }

    public function test_a_postcode_is_looked_up_when_a_token_is_configured(): void
    {
        config(['services.mapbox.token' => 'pk.test_token']);
        $this->fakeThreeAgencies();

        // Postnummer 702 xx ligger i Örebro.
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [[
                'geometry' => ['coordinates' => [15.21, 59.27]],
                'properties' => ['context' => ['place' => ['name' => 'Örebro']]],
            ]]]),
        ]);

        $this->get('/byrakartan?q='.urlencode('702 22'))
            ->assertOk()
            ->assertSeeInOrder(['Kvarter Byrå Örebro', 'Fokus Revision', 'Vass Byrå Umeå'])
            ->assertSee('närmast 702 22 Örebro')
            ->assertSee('data-origin="59.27,15.21"', false);

        // Bara postnumret skickas till Mapbox, och ett postnummer slås bara upp en gång.
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.mapbox.com/search/geocode/v6/forward')
            && $request['q'] === '70222' && $request['types'] === 'postcode' && $request['country'] === 'se');

        $this->get('/byrakartan?q='.urlencode('70222'))->assertOk()->assertSee('närmast 702 22 Örebro');

        Http::assertSentCount(2); // byråerna + postnumret (cachat andra gången)
    }

    public function test_a_postcode_without_a_token_or_match_gives_a_friendly_notice(): void
    {
        config(['services.mapbox.token' => null]);
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?q=70222')
            ->assertOk()
            ->assertSee('Vi hittade inte postnumret')
            ->assertDontSee('närmast');

        config(['services.mapbox.token' => 'pk.test_token']);
        Http::swap(new Factory);
        Http::fake([
            self::API => Http::response(['data' => [], 'meta' => ['sidor' => 1]]),
            'api.mapbox.com/*' => Http::response('nej', 500),
        ]);
        Cache::flush();

        $this->get('/byrakartan?q=99999')->assertOk();
    }

    public function test_the_hero_map_gets_the_agencies_in_the_result(): void
    {
        config(['services.mapbox.token' => 'pk.public_token', 'services.mapbox.style' => 'mapbox/streets-v12']);
        $this->fakeThreeAgencies();

        $response = $this->get('/byrakartan?digital=1')->assertOk()
            ->assertSee('data-agency-map', false)
            ->assertSee('data-mapbox-token="pk.public_token"', false)
            ->assertSee('data-mapbox-style="mapbox/streets-v12"', false)
            ->assertSee('id="byraer-data"', false);

        $markers = $this->agenciesJson($response->getContent());

        // Kartan följer filtret: bara de digitala byråerna.
        $this->assertSame(['Fokus Revision & Redovisning', 'Vass Byrå Umeå'], array_column($markers, 'name'));
        $this->assertSame(59.33, $markers[0]['lat']);
        $this->assertSame('4,2', $markers[0]['rating']);
        $this->assertNull($markers[1]['rating']);
    }

    public function test_the_list_has_a_colourful_map_and_the_agency_pages_a_light_one(): void
    {
        config(['services.mapbox.token' => 'pk.public_token']);
        $this->fakeThreeAgencies();

        // Standardstilarna.
        config(['services.mapbox.style' => 'mapbox/streets-v12', 'services.mapbox.style_agency' => 'mapbox/light-v11']);

        $this->get('/byrakartan')->assertOk()->assertSee('data-mapbox-style="mapbox/streets-v12"', false);
        $this->get('/byrakartan/kvarter-orebro')->assertOk()->assertSee('data-mapbox-style="mapbox/light-v11"', false);

        // Var och en kan bytas, bland annat till en egen stil från Mapbox Studio.
        config(['services.mapbox.style' => 'mapbox/outdoors-v12', 'services.mapbox.style_agency' => 'oqto/ljus-stil-1']);

        $this->get('/byrakartan')->assertOk()->assertSee('data-mapbox-style="mapbox/outdoors-v12"', false);
        $this->get('/byrakartan/kvarter-orebro')->assertOk()->assertSee('data-mapbox-style="oqto/ljus-stil-1"', false);
    }

    public function test_an_invalid_map_style_falls_back_to_the_default_of_each_page(): void
    {
        config(['services.mapbox.token' => 'pk.public_token', 'services.mapbox.style' => '"><script>', 'services.mapbox.style_agency' => 'ingen-stil']);
        $this->fakeThreeAgencies();

        $this->get('/byrakartan')->assertOk()->assertSee('data-mapbox-style="mapbox/streets-v12"', false);
        $this->get('/byrakartan/kvarter-orebro')->assertOk()->assertSee('data-mapbox-style="mapbox/light-v11"', false);
    }

    public function test_only_a_public_mapbox_token_is_ever_sent_to_the_browser(): void
    {
        config(['services.mapbox.token' => 'sk.hemlig_token']);
        $this->fakeThreeAgencies();

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('data-agency-map', false)
            ->assertSee('data-mapbox-token=""', false)
            ->assertDontSee('sk.hemlig_token');
    }

    public function test_the_map_data_cannot_break_out_of_the_page(): void
    {
        $this->fakeApi([$this->row('ond', 'Ond </script><script>alert(1)</script> Byrå', 'Lund')]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertDontSee('<script>alert(1)', false);
    }

    public function test_there_is_no_map_when_no_agency_has_a_position(): void
    {
        $this->fakeApi([$this->row('a', 'Byrå A', 'Lund', ['lat' => null, 'lng' => null])]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('Byrå A')
            ->assertDontSee('data-agency-map', false);
    }

    public function test_unknown_filter_values_are_ignored(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan?stad='.urlencode('<script>alert(1)</script>').'&tjanst[]=x')
            ->assertOk()
            ->assertSee('Kvarter Byrå Örebro')
            ->assertSee('Vass Byrå Umeå')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_it_only_calls_the_api_once_while_the_cache_is_fresh(): void
    {
        $this->fakeThreeAgencies();

        $this->get('/byrakartan')->assertOk();
        $this->get('/byrakartan?stad=Lund')->assertOk();
        $this->get('/byrakartan')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_the_page_survives_the_api_being_down_without_any_data(): void
    {
        Http::fake([self::API => Http::response('Service unavailable', 503)]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('Listan med byråer är inte tillgänglig just nu');

        // Sidfoten länkar inte till en tom sida.
        $this->get('/')->assertDontSee('href="/byrakartan"', false);
    }

    public function test_the_page_survives_a_connection_failure(): void
    {
        Http::fake([self::API => fn () => throw new ConnectionException('DNS')]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('inte tillgänglig just nu');
    }

    public function test_it_does_not_hammer_the_api_after_a_failure(): void
    {
        Http::fake([self::API => Http::response('nej', 500)]);

        $this->get('/byrakartan')->assertOk();
        $count = count(Http::recorded());

        $this->get('/byrakartan')->assertOk();
        $this->get('/byrakartan')->assertOk();

        $this->assertSame($count, count(Http::recorded()));
    }

    public function test_it_shows_the_last_known_list_when_the_api_goes_down(): void
    {
        $this->fakeThreeAgencies();
        $this->get('/byrakartan')->assertOk()->assertSee('Kvarter Byrå Örebro');

        // Den färska cachen löper ut, men den senast kända kopian finns kvar.
        Cache::forget('byrakartan.agencies.v2');
        $this->replaceFake([self::API => Http::response('nere', 502)]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('Kvarter Byrå Örebro')
            ->assertDontSee('inte tillgänglig just nu');
    }

    public function test_a_malformed_response_is_treated_as_a_failure(): void
    {
        Http::fake([self::API => Http::response(['fel' => 'ingen data'], 200)]);

        $this->get('/byrakartan')->assertOk()->assertSee('inte tillgänglig just nu');
    }

    public function test_unsafe_urls_and_markup_in_the_data_are_neutralised(): void
    {
        $this->fakeApi([
            $this->row('ond', '<b>Ond</b> Byrå', 'Lund', [
                'webbplats' => 'javascript:alert(1)',
                'tjanster' => [['slug' => 'x', 'namn' => '<img src=x onerror=alert(1)>Revision']],
            ]),
            $this->row('', '', 'Lund'),
        ]);

        $this->get('/byrakartan')
            ->assertOk()
            ->assertSee('Ond Byrå')
            ->assertDontSee('javascript:', false)
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('<b>Ond</b>', false);

        $this->assertCount(1, app(AgencyDirectory::class)->agencies());
    }

    public function test_pages_with_many_agencies_are_fetched_page_by_page(): void
    {
        Http::fake([
            self::API => Http::sequence()
                ->push(['data' => [$this->row('a', 'Byrå A', 'Lund')], 'meta' => ['sidor' => 2]])
                ->push(['data' => [$this->row('b', 'Byrå B', 'Malmö')], 'meta' => ['sidor' => 2]]),
        ]);

        $this->get('/byrakartan')->assertOk()->assertSee('Byrå A')->assertSee('Byrå B');
    }

    public function test_the_footer_links_to_the_list_only_when_there_is_something_cached(): void
    {
        $this->get('/')->assertDontSee('href="/byrakartan"', false);

        $this->fakeThreeAgencies();
        app(AgencyDirectory::class)->refresh();

        $this->get('/')->assertSee('href="/byrakartan"', false);
    }

    public function test_the_sync_command_fills_the_cache_and_reports_failure(): void
    {
        $this->fakeThreeAgencies();
        $this->artisan('byrakartan:sync')->expectsOutputToContain('Klart. 3 byråer.')->assertSuccessful();

        $this->replaceFake([self::API => Http::response('nere', 500)]);
        $this->artisan('byrakartan:sync')->assertFailed();

        // Den gamla listan ligger kvar.
        $this->assertCount(3, app(AgencyDirectory::class)->cached());
    }
}
