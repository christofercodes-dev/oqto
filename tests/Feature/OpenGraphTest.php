<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenGraphTest extends TestCase
{
    /**
     * @return array<int, array{0: string}>
     */
    public static function pages(): array
    {
        return [
            ['/'], ['/funktioner'], ['/priser'], ['/faqs'], ['/kontakt'], ['/om-oss'], ['/jobb'],
            ['/boka-demo'], ['/for-utvecklare'], ['/integrationer'], ['/villkor'], ['/support'],
            ['/for-foretagare'], ['/for-foretagare/skattekonto'], ['/byra/enora'],
            ['/integrationer/ekosystem'], ['/integrationer/ekosystem/hino-reda-oqto'], ['/integrationer/a-count-apps'],
            ['/jobb/customer-success'], ['/investerartraff'], ['/byra/enora/skattekonto'],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_page_has_a_share_image_title_and_url(string $url): void
    {
        $response = $this->get($url);

        $this->assertContains($response->getStatusCode(), [200], "{$url} svarade inte 200.");

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]+/images/og/oqto-default\.png">#', $html, "{$url} saknar og:image.");
        $this->assertMatchesRegularExpression('#<meta name="twitter:image" content="https?://[^"]+">#', $html, "{$url} saknar twitter:image.");
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:title" content="[^"]+">#', $html, "{$url} saknar og:title.");
        $this->assertMatchesRegularExpression('#<meta property="og:description" content="[^"]+">#', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:url" content="https?://[^"]+">#', $html);
    }

    public function test_every_published_page_and_detail_page_has_a_share_image(): void
    {
        foreach (['pages', 'integrations', 'ekosystem', 'foretagare_funktioner', 'open_positions'] as $collection) {
            foreach (\Statamic\Facades\Entry::query()->where('collection', $collection)->where('published', true)->get() as $entry) {
                $url = $entry->url();

                if (! $url) {
                    continue;
                }

                $response = $this->get($url);

                if ($response->getStatusCode() !== 200) {
                    continue;
                }

                $this->assertStringContainsString('<meta property="og:image" content="', $response->getContent(), "{$url} saknar og:image.");
            }
        }
    }

    public function test_the_not_found_page_has_a_share_image_too(): void
    {
        $this->get('/finns-inte-alls')
            ->assertNotFound()
            ->assertSee('<meta property="og:image" content="', false);
    }

    public function test_a_page_can_pick_its_own_share_image(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));
        $fieldset = file_get_contents(resource_path('fieldsets/seo.yaml'));

        $this->assertStringContainsString('{{ og_image:permalink }}', $layout);
        $this->assertStringContainsString('handle: og_image', $fieldset);
    }

    public function test_the_default_share_image_exists_and_is_1200_by_630(): void
    {
        $path = public_path('images/og/oqto-default.png');

        $this->assertFileExists($path);

        [$width, $height] = getimagesize($path);

        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
    }
}
