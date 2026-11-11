<?php

namespace Tests\Feature;

use Statamic\Facades\Entry;
use Statamic\View\View;
use Tests\TestCase;

class ReviewLogoTest extends TestCase
{
    protected array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $entry) {
            $entry->delete();
        }

        parent::tearDown();
    }

    protected function review(string $slug, array $data): string
    {
        $entry = Entry::make()->collection('reviews')->slug('zz-test-'.$slug)->published(true)
            ->data(array_merge(['title' => 'Test Testsson', 'company' => 'Testbolaget AB', 'quote' => 'Bra!'], $data));
        $entry->save();
        $this->created[] = $entry;

        return $entry->id();
    }

    protected function render(array $ids): string
    {
        return (string) (new View)
            ->template('components/reviews')
            ->with(['reviews_title' => 'Omdömen', 'reviews_items' => Entry::query()->whereIn('id', $ids)->get()])
            ->render();
    }

    public function test_logo_links_externally_when_website_is_set(): void
    {
        $id = $this->review('med-lank', ['logo' => ['byraer/digim.png'], 'website' => 'https://example.com']);

        $html = $this->render([$id]);

        $this->assertStringContainsString('<a href="https://example.com" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('src="/assets/byraer/digim.png"', $html);
    }

    public function test_logo_without_website_is_not_linked_and_no_logo_shows_nothing(): void
    {
        $utanLank = $this->review('utan-lank', ['logo' => ['byraer/digim.png']]);
        $utanLogga = $this->review('utan-logga', ['website' => 'https://example.com']);

        $html = $this->render([$utanLank]);
        $this->assertStringContainsString('reviews-logo', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);

        $html = $this->render([$utanLogga]);
        $this->assertStringNotContainsString('reviews-logo', $html);
        $this->assertStringNotContainsString('example.com', $html);
    }
}
