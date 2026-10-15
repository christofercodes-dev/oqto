<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;

class SupportStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.status.url' => 'https://app.example.test',
            'services.status.slow_ms' => 3000,
            'services.status.cache_seconds' => 20,
            // Ingen Intercom-hämtning i de här testerna, och oberoende av lokala .env-värden.
            'services.intercom.token' => null,
            'services.intercom.collection_ids' => [],
            'services.intercom.featured_article_ids' => [],
        ]);

        Cache::flush();
    }

    public function test_reports_operational_when_the_app_responds(): void
    {
        Http::fake(['app.example.test*' => Http::response('<html>login</html>', 200)]);

        $this->getJson('/support/status/check')
            ->assertOk()
            ->assertJsonPath('status', 'operational')
            ->assertJsonPath('http_status', 200)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_redirects_and_client_errors_count_as_up(): void
    {
        Http::fake(['app.example.test*' => Http::response('', 401)]);

        $this->getJson('/support/status/check')->assertJsonPath('status', 'operational')->assertJsonPath('http_status', 401);
    }

    public function test_reports_down_on_server_error(): void
    {
        Http::fake(['app.example.test*' => Http::response('Bad gateway', 502)]);

        $this->getJson('/support/status/check')->assertOk()->assertJsonPath('status', 'down')->assertJsonPath('http_status', 502);
    }

    public function test_reports_down_when_the_app_cannot_be_reached(): void
    {
        Http::fake(['app.example.test*' => fn () => throw new ConnectionException('timeout')]);

        $this->getJson('/support/status/check')
            ->assertOk()
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('http_status', null)
            ->assertJsonPath('response_ms', null);
    }

    public function test_reports_degraded_when_the_app_is_slow(): void
    {
        config(['services.status.slow_ms' => -1]);

        Http::fake(['app.example.test*' => Http::response('ok', 200)]);

        $this->getJson('/support/status/check')->assertJsonPath('status', 'degraded');
    }

    public function test_result_is_cached_so_many_visitors_give_one_request(): void
    {
        Http::fake(['app.example.test*' => Http::response('ok', 200)]);

        $this->getJson('/support/status/check');
        $this->getJson('/support/status/check');
        $this->getJson('/support/status/check');

        Http::assertSentCount(1);
    }

    public function test_support_page_shows_status_and_contact_links_and_does_not_wait_for_the_app(): void
    {
        Http::fake();

        $this->get('/support')
            ->assertOk()
            ->assertSee('Hur kan vi hjälpa dig?')
            ->assertSee('app.example.test')
            ->assertSee('href="/kontakt"', false)
            ->assertSee('href="/boka-demo"', false);

        // Sidan ritas upp utan att kontrollera appen - det gör sidans skript via /support/status/check.
        Http::assertNothingSent();
    }

    public function test_featured_guides_are_shown_side_by_side_with_a_button_to_all_guides(): void
    {
        config(['services.intercom.token' => 'test-token', 'services.intercom.featured_article_ids' => ['12', '10']]);

        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [['id' => '1', 'name' => 'Flytta till Oqto', 'parent_id' => null, 'order' => 1]],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    ['id' => '10', 'title' => 'Via integration', 'body' => '<p>a</p>', 'state' => 'published', 'parent_ids' => [1]],
                    ['id' => '11', 'title' => 'Exportera filer', 'body' => '<p>b</p>', 'state' => 'published', 'parent_ids' => [1]],
                    ['id' => '12', 'title' => 'Importguiden Spiris', 'body' => '<p>c</p>', 'state' => 'published', 'parent_ids' => [1]],
                ],
            ]),
        ]);

        $html = $this->get('/support')->assertOk()->getContent();

        // Bara de två utvalda, i den ordning de angetts.
        $this->assertStringContainsString('Importguiden Spiris', $html);
        $this->assertStringContainsString('Via integration', $html);
        $this->assertStringNotContainsString('Exportera filer', $html);
        $this->assertLessThan(strpos($html, 'Via integration'), strpos($html, 'Importguiden Spiris'));

        // Knappen till alla guider ligger under korten.
        $this->assertStringContainsString('class="support-button support-button-secondary"', $html);
        $this->assertMatchesRegularExpression('~href="/support/flytta-till-oqto"[^>]*support-button~', $html);
    }

    public function test_old_status_url_redirects_to_the_support_page(): void
    {
        $this->get('/support/status')->assertRedirect('/support#driftstatus')->assertStatus(301);
    }

    public function test_support_page_links_to_the_move_articles(): void
    {
        config(['services.intercom.token' => 'test-token', 'services.intercom.collection_ids' => []]);

        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    ['id' => '1', 'name' => 'Flytta till Oqto', 'parent_id' => null, 'order' => 1],
                    ['id' => '2', 'name' => 'Bokföring', 'parent_id' => null, 'order' => 2],
                ],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [
                    ['id' => '10', 'title' => 'Importguide Fortnox', 'description' => 'Så importerar du.', 'body' => '<p>a</p>', 'state' => 'published', 'parent_ids' => [1]],
                    ['id' => '11', 'title' => 'Månadsavslut', 'body' => '<p>b</p>', 'state' => 'published', 'parent_ids' => [2]],
                ],
            ]),
        ]);

        $this->get('/support')
            ->assertOk()
            ->assertSee('Ska du flytta till Oqto?')
            ->assertSee('Importguide Fortnox')
            ->assertSee('/support/flytta-till-oqto/importguide-fortnox', false)
            // Bara flytt-kategorin, inte övriga artiklar.
            ->assertDontSee('Månadsavslut');
    }

    public function test_a_category_can_not_take_the_status_slug(): void
    {
        config(['services.intercom.token' => 'test-token', 'services.intercom.collection_ids' => []]);

        Http::fake([
            'api.intercom.io/help_center/collections*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [['id' => '1', 'name' => 'Status', 'parent_id' => null, 'order' => 1]],
            ]),
            'api.intercom.io/articles*' => Http::response([
                'pages' => ['total_pages' => 1],
                'data' => [['id' => '10', 'title' => 'Något', 'body' => '<p>a</p>', 'state' => 'published', 'parent_ids' => [1]]],
            ]),
        ]);

        $slug = app(\App\Services\Intercom\HelpCenter::class)->refresh()['categories'][0]['slug'];

        $this->assertNotSame('status', $slug);
    }
}
