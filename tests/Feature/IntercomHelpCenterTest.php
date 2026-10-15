<?php

namespace Tests\Feature;

use App\Services\Intercom\HelpCenter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntercomHelpCenterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.intercom.token' => 'test-token',
            'services.intercom.client_secret' => 'test-secret',
            'services.intercom.base_url' => 'https://api.intercom.io',
            'services.intercom.locale' => 'sv',
            // Oberoende av vad som står i den lokala .env.
            'services.intercom.collection_ids' => [],
        ]);

        Cache::flush();
    }

    protected function fakeIntercom(): void
    {
        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'type' => 'list',
                'pages' => ['page' => 1, 'per_page' => 50, 'total_pages' => 1],
                'data' => [
                    ['id' => '10', 'name' => 'Kom igång', 'description' => 'Allt du behöver för att börja.', 'parent_id' => null, 'order' => 1],
                    ['id' => '11', 'name' => 'Dataflytt', 'description' => null, 'parent_id' => '10', 'order' => 1],
                    ['id' => '20', 'name' => 'Fakturering', 'description' => 'Kundfakturor.', 'parent_id' => null, 'order' => 2],
                ],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'type' => 'list',
                'pages' => ['page' => 1, 'per_page' => 50, 'total_pages' => 1],
                'data' => [
                    [
                        'id' => '100', 'title' => 'Flytta från Fortnox', 'description' => 'Så flyttar du.',
                        'body' => '<p>Hej <strong>där</strong></p><script>alert(1)</script><img src="x" onerror="alert(2)">',
                        'state' => 'published', 'parent_id' => '11', 'updated_at' => 1790000000, 'url' => 'https://help.example/100',
                    ],
                    [
                        'id' => '101', 'title' => 'Skapa en faktura', 'description' => 'Kom igång med fakturering.',
                        'body' => '<p>Fakturera.</p>', 'state' => 'published', 'parent_id' => '20', 'updated_at' => 1790000100,
                    ],
                    [
                        'id' => '102', 'title' => 'Utkast', 'body' => '<p>Ej klar</p>', 'state' => 'draft', 'parent_id' => '20',
                    ],
                    [
                        'id' => '103', 'title' => 'Översatt artikel', 'description' => 'English', 'body' => '<p>English</p>',
                        'state' => 'published', 'parent_id' => '20', 'default_locale' => 'en',
                        'translated_content' => ['sv' => ['title' => 'Svensk titel', 'description' => 'Svenskt', 'body' => '<p>Svenskt</p>']],
                    ],
                ],
            ]),
        ]);
    }

    protected function signed(array $payload, string $secret = 'test-secret'): array
    {
        $body = json_encode($payload);

        return [$body, ['X-Hub-Signature' => 'sha1='.hash_hmac('sha1', $body, $secret), 'Content-Type' => 'application/json']];
    }

    public function test_builds_categories_and_articles_from_intercom(): void
    {
        $this->fakeIntercom();

        $data = app(HelpCenter::class)->refresh();

        $this->assertSame(['Kom igång', 'Fakturering'], collect($data['categories'])->pluck('title')->all());

        $getStarted = collect($data['categories'])->firstWhere('slug', 'kom-igang');
        $this->assertSame(1, $getStarted['article_count']);
        // Artikeln ligger i underkategorin "Dataflytt" - blir en sektion i "Kom igång".
        $this->assertSame('Dataflytt', $getStarted['sections'][0]['title']);

        // Utkast visas inte, och svensk översättning används när den finns.
        $titles = collect($data['articles'])->pluck('title');
        $this->assertFalse($titles->contains('Utkast'));
        $this->assertTrue($titles->contains('Svensk titel'));
        $this->assertSame('/support/kom-igang/flytta-fran-fortnox', collect($data['articles'])->firstWhere('id', '100')['url']);
    }

    public function test_article_html_is_sanitized(): void
    {
        $this->fakeIntercom();

        $body = collect(app(HelpCenter::class)->refresh()['articles'])->firstWhere('id', '100')['body'];

        $this->assertStringContainsString('<strong>där</strong>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_long_headings_become_lead_paragraphs(): void
    {
        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [['id' => '1', 'name' => 'Flytt', 'parent_id' => null, 'order' => 1]],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [[
                    'id' => '10', 'title' => 'Guide', 'state' => 'published', 'parent_ids' => [1],
                    'body' => '<h3>Importguiden hittar du under Inställningar i Oqto. Om du istället vill flytta från Fortnox via <a href="https://example.com">integrationen</a> - följ denna rutin.</h3>'
                        .'<h3><b>Steg 1 – System</b></h3><p>Text</p>',
                ]],
            ]),
        ]);

        $body = collect(app(HelpCenter::class)->refresh()['articles'])->firstWhere('id', '10')['body'];

        // Den långa meningen blir ett stycke (med länken kvar) ...
        $this->assertStringContainsString('<p class="support-lead">Importguiden hittar du', $body);
        $this->assertStringContainsString('<a href="https://example.com">integrationen</a>', $body);
        // ... medan en kort, riktig rubrik förblir en rubrik.
        $this->assertMatchesRegularExpression('~<h3[^>]*><b>Steg 1 – System</b></h3>~u', $body);
    }

    public function test_support_pages_render(): void
    {
        $this->fakeIntercom();

        $this->get('/support')->assertOk()->assertSee('Hur kan vi hjälpa dig?')->assertSee('Kontrollerar')->assertSee('href="/kontakt"', false);

        $this->get('/support/kom-igang')->assertOk()->assertSee('Flytta från Fortnox')->assertSee('Dataflytt');

        $this->get('/support/kom-igang/flytta-fran-fortnox')
            ->assertOk()
            ->assertSee('Flytta från Fortnox')
            ->assertSee('<strong>där</strong>', false)
            ->assertDontSee('alert(1)', false);

        $this->get('/support/finns-inte')->assertNotFound();
        $this->get('/support/kom-igang/finns-inte')->assertNotFound();
    }

    public function test_collection_filter_limits_articles_and_supports_parent_ids(): void
    {
        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    ['id' => '1', 'name' => 'Flytta till Oqto', 'description' => 'Guide', 'parent_id' => null, 'order' => 1],
                    ['id' => '2', 'name' => 'Bank &amp; Skatt', 'parent_id' => null, 'order' => 2],
                ],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    // Ligger i flera samlingar: parent_id tomt, parent_ids satt.
                    ['id' => '10', 'title' => 'Importguide', 'body' => '<p>a</p>', 'state' => 'published', 'parent_id' => null, 'parent_ids' => [1]],
                    ['id' => '11', 'title' => 'Koppla bank', 'body' => '<p>b</p>', 'state' => 'published', 'parent_id' => 2, 'parent_ids' => [2]],
                    ['id' => '12', 'title' => 'Utan samling', 'body' => '<p>c</p>', 'state' => 'published', 'parent_id' => null],
                ],
            ]),
        ]);

        config(['services.intercom.collection_ids' => ['1']]);

        $data = app(HelpCenter::class)->refresh();

        $this->assertSame(['Importguide'], collect($data['articles'])->pluck('title')->all());
        $this->assertSame(['Flytta till Oqto'], collect($data['categories'])->pluck('title')->all());

        // Utan filter kommer allt med, och entiteter i namn avkodas.
        config(['services.intercom.collection_ids' => []]);

        $all = app(HelpCenter::class)->refresh();

        $this->assertContains('Bank & Skatt', collect($all['categories'])->pluck('title')->all());
        $this->assertCount(3, $all['articles']);
    }

    public function test_links_between_articles_are_rewritten_to_the_site(): void
    {
        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [['id' => '1', 'name' => 'Flytt', 'parent_id' => null, 'order' => 1]],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    [
                        'id' => '10', 'content_id' => '900', 'title' => 'Guide', 'state' => 'published', 'parent_ids' => [1],
                        'url' => 'https://intercom-help.eu/oqto/sv/articles/10-guide',
                        'body' => '<p><a href="https://intercom-help.eu/oqto/sv/articles/11-export">a</a> '
                            .'<a href="https://app.eu.intercom.com/a/apps/x/settings/helpcenter/1/collections?activeContentId=901&amp;editorMode=view">b</a> '
                            .'<a href="https://example.com/articles/11-extern">c</a></p>',
                    ],
                    [
                        'id' => '11', 'content_id' => '901', 'title' => 'Export', 'body' => '<p>x</p>', 'state' => 'published', 'parent_ids' => [1],
                        'url' => 'https://intercom-help.eu/oqto/sv/articles/11-export',
                    ],
                ],
            ]),
        ]);

        $body = collect(app(HelpCenter::class)->refresh()['articles'])->firstWhere('id', '10')['body'];

        // Både den publika och den inklistrade admin-länken pekar nu på sajten.
        $this->assertSame(2, substr_count($body, 'href="/support/flytt/export"'));
        // Externa sajter lämnas orörda.
        $this->assertStringContainsString('https://example.com/articles/11-extern', $body);
        $this->assertStringNotContainsString('intercom', $body);
    }

    public function test_page_still_renders_when_intercom_is_down(): void
    {
        Http::fake(['*' => Http::response('fel', 500)]);

        $this->get('/support')->assertOk()->assertSee('href="/kontakt"', false)->assertDontSee('Ska du flytta till Oqto?');
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        Http::fake();

        [$body] = $this->signed(['topic' => 'conversation.user.created']);

        $this->call('POST', '/webhooks/intercom', [], [], [], [
            'HTTP_X_HUB_SIGNATURE' => 'sha1='.hash_hmac('sha1', $body, 'fel-hemlighet'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->call('POST', '/webhooks/intercom', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_webhook_ping_is_acknowledged_without_refresh(): void
    {
        Http::fake();

        [$body, $headers] = $this->signed(['type' => 'notification_event', 'topic' => 'ping']);

        $this->call('POST', '/webhooks/intercom', [], [], [], $this->server($headers), $body)->assertOk();

        Http::assertNothingSent();
    }

    public function test_valid_webhook_refreshes_the_help_center(): void
    {
        $this->fakeIntercom();

        // Gammal cache som ska ersättas.
        Cache::put('intercom.help_center', ['categories' => [], 'articles' => [], 'synced_at' => 'gammal'], 3600);

        [$body, $headers] = $this->signed(['type' => 'notification_event', 'topic' => 'conversation.admin.replied']);

        $this->call('POST', '/webhooks/intercom', [], [], [], $this->server($headers), $body)->assertOk();

        $this->assertCount(3, Cache::get('intercom.help_center')['articles']);
    }

    public function test_webhook_without_configured_secret_is_unavailable(): void
    {
        config(['services.intercom.client_secret' => null]);

        $this->call('POST', '/webhooks/intercom', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(503);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function server(array $headers): array
    {
        return [
            'HTTP_X_HUB_SIGNATURE' => $headers['X-Hub-Signature'],
            'CONTENT_TYPE' => 'application/json',
        ];
    }
}
