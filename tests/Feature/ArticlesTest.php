<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ArticlesTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function article(string $slug, bool $published = true, ?Carbon $date = null, array $data = []): EntryContract
    {
        $entry = Entry::make()
            ->collection('artiklar')
            ->slug(self::PREFIX.$slug)
            ->date($date ?? now()->subDay())
            ->published($published)
            ->data(array_merge([
                'title' => 'Artikel '.$slug,
                'excerpt' => 'Ingress för '.$slug,
                'category' => 'Bokföring',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Brödtext för '.$slug.'.']]],
                ],
            ], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    public function test_the_list_does_not_exist_until_something_is_published(): void
    {
        $this->article('utkast', published: false);
        $this->article('schemalagd', date: now()->addWeek());

        $this->get('/artiklar')->assertNotFound();
    }

    public function test_a_draft_is_never_shown_publicly(): void
    {
        $this->article('utkast', published: false);

        $this->get('/artiklar/'.self::PREFIX.'utkast')->assertNotFound();
    }

    public function test_a_scheduled_article_is_hidden_until_its_date(): void
    {
        $this->article('kommande', date: now()->addWeek());

        $this->get('/artiklar/'.self::PREFIX.'kommande')->assertNotFound();
    }

    public function test_a_published_article_is_shown_with_its_content_and_listed(): void
    {
        $this->article('klar');

        $this->get('/artiklar/'.self::PREFIX.'klar')
            ->assertOk()
            ->assertSee('Artikel klar')
            ->assertSee('Ingress för klar')
            ->assertSee('Brödtext för klar.')
            ->assertSee('Bokföring')
            ->assertSee('min läsning')
            ->assertSee('href="/boka-demo"', false);

        $this->get('/artiklar')
            ->assertOk()
            ->assertSee('Artikel klar')
            ->assertSee('/artiklar/'.self::PREFIX.'klar', false);
    }

    public function test_drafts_and_scheduled_articles_stay_out_of_the_list(): void
    {
        $this->article('klar');
        $this->article('utkast', published: false);
        $this->article('kommande', date: now()->addWeek());

        $this->get('/artiklar')
            ->assertOk()
            ->assertSee('Artikel klar')
            ->assertDontSee('Artikel utkast')
            ->assertDontSee('Artikel kommande');
    }

    public function test_other_articles_are_suggested_below_an_article(): void
    {
        $this->article('ett');
        $this->article('tva');

        $this->get('/artiklar/'.self::PREFIX.'ett')
            ->assertOk()
            ->assertSee('Fler artiklar')
            ->assertSee('Artikel tva');
    }
}
