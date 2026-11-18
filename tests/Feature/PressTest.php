<?php

namespace Tests\Feature;

use App\Scopes\PressContacts;
use App\Services\Press\PressCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\TestCase;

class PressTest extends TestCase
{
    protected const PREFIX = 'zz-test-';

    /** @var array<int, EntryContract> */
    protected array $created = [];

    /** @var array<int, string> */
    protected array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $entry) {
            $entry->delete();
        }

        File::delete($this->files);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function item(string $slug, bool $published = true, ?Carbon $date = null, array $data = []): EntryContract
    {
        $entry = Entry::make()
            ->collection('press')
            ->blueprint('pressmeddelande')
            ->slug(self::PREFIX.$slug)
            ->date($date ?? now()->subDay())
            ->published($published)
            ->data(array_merge([
                'title' => 'Press '.$slug,
                'summary' => 'Sammanfattning för '.$slug,
                'category' => 'integration',
                'body' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Brödtext för '.$slug.'.']]],
                ],
            ], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function teamMember(string $slug, array $data = []): EntryContract
    {
        $entry = Entry::make()
            ->collection('team')
            ->blueprint('team_member')
            ->slug(self::PREFIX.$slug)
            ->data(array_merge(['title' => 'Person '.$slug], $data));

        $entry->save();

        return $this->created[] = $entry;
    }

    protected function feed(): \SimpleXMLElement
    {
        $response = $this->get('/press/feed.xml')->assertOk();

        $this->assertStringStartsWith('application/rss+xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Flödet är inte välformad XML.');

        return $xml;
    }

    /** @return array<string, \SimpleXMLElement> */
    protected function feedItems(): array
    {
        $items = [];

        foreach ($this->feed()->channel->item as $item) {
            $items[(string) $item->title] = $item;
        }

        return $items;
    }

    public function test_drafts_and_scheduled_items_are_never_public(): void
    {
        $this->item('utkast', published: false);
        $this->item('schemalagd', date: now()->addWeek());

        $this->get('/press/'.self::PREFIX.'utkast')->assertNotFound();
        $this->get('/press/'.self::PREFIX.'schemalagd')->assertNotFound();
        $this->get('/press')->assertOk()->assertDontSee('Press utkast')->assertDontSee('Press schemalagd');

        $titles = array_keys($this->feedItems());
        $this->assertNotContains('Press utkast', $titles);
        $this->assertNotContains('Press schemalagd', $titles);
    }

    public function test_the_index_lists_published_items_newest_first_with_category_filter(): void
    {
        $this->item('aldre', date: now()->subDays(5), data: ['category' => 'integration']);
        $this->item('nyare', date: now()->subDay(), data: ['category' => 'team_news']);

        $html = $this->get('/press')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Press aldre'), strpos($html, 'Press nyare'));
        $this->assertStringContainsString('Sammanfattning för nyare', $html);
        $this->assertStringContainsString('Teamnyheter', $html);
        $this->assertStringContainsString('href="/press?kategori=product_update"', $html);

        $this->get('/press?kategori=team_news')->assertOk()->assertSee('Press nyare')->assertDontSee('Press aldre');
        $this->get('/press?kategori=integration')->assertOk()->assertSee('Press aldre')->assertDontSee('Press nyare');
        $this->get('/press?kategori=finns-inte')->assertOk()->assertSee('Press nyare')->assertSee('Press aldre');
    }

    public function test_the_item_page_shows_content_share_tags_and_feed_link(): void
    {
        $this->item('visa', data: [
            'social_title' => 'Egen delningstitel',
            'social_description' => 'Egen delningsbeskrivning',
        ]);

        $response = $this->get('/press/'.self::PREFIX.'visa')->assertOk();

        $response->assertSee('Press visa')->assertSee('Brödtext för visa.')->assertSee('Integration');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('#<meta property="og:title" content="Egen delningstitel">#', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:description" content="Egen delningsbeskrivning">#', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:type" content="article">#', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:url" content="https?://[^"]+/press/'.self::PREFIX.'visa">#', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]+/images/press/integration\.png">#', $html);
        $this->assertMatchesRegularExpression('#<meta name="twitter:image" content="https?://[^"]+/images/press/integration\.png">#', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertMatchesRegularExpression('#<link rel="alternate" type="application/rss\+xml"[^>]+/press/feed\.xml">#', $html);
    }

    public function test_share_tags_fall_back_to_title_and_summary(): void
    {
        $this->item('reserv');

        $html = $this->get('/press/'.self::PREFIX.'reserv')->getContent();

        $this->assertStringContainsString('<meta property="og:title" content="Press reserv">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Sammanfattning för reserv">', $html);
    }

    public function test_the_index_links_to_the_feed(): void
    {
        $this->get('/press')->assertOk()->assertSee('rel="alternate" type="application/rss+xml"', false);
    }

    public function test_an_item_always_has_a_cover_image(): void
    {
        $catalog = app(PressCatalog::class);

        foreach (array_keys(PressCatalog::CATEGORIES) as $category) {
            $entry = $this->item('omslag-'.$category, data: ['category' => $category]);

            $this->assertSame(PressCatalog::DEFAULT_IMAGES[$category], $catalog->coverUrl($entry));
            $this->assertFileExists(public_path(PressCatalog::DEFAULT_IMAGES[$category]));
        }

        // En bild som pekar på en fil som inte finns ger också standardbilden.
        $broken = $this->item('trasig', data: ['cover_image' => ['finns-inte.jpg']]);
        $this->assertSame('/images/press/integration.png', $catalog->coverUrl($broken));
    }

    public function test_an_uploaded_cover_image_is_used_on_the_page_and_in_the_feed(): void
    {
        File::ensureDirectoryExists(public_path('press-media'));
        $file = public_path('press-media/zz-test-omslag.png');
        copy(public_path('images/press/integration.png'), $file);
        $this->files[] = $file;

        $this->item('egenbild', data: ['cover_image' => ['zz-test-omslag.png']]);

        $this->get('/press/'.self::PREFIX.'egenbild')
            ->assertSee('<meta property="og:image" content="', false)
            ->assertSee('/press-media/zz-test-omslag.png', false);

        $item = $this->feedItems()['Press egenbild'];
        $this->assertStringEndsWith('/press-media/zz-test-omslag.png', (string) $item->enclosure['url']);
        $this->assertSame((string) filesize($file), (string) $item->enclosure['length']);
        $this->assertSame('image/png', (string) $item->enclosure['type']);
    }

    public function test_the_feed_is_valid_rss_with_rfc_822_dates(): void
    {
        $this->item('flode', date: Carbon::parse('2026-03-04 09:30:00', 'UTC'), data: [
            'linkedin_caption' => "Rad ett\nRad två",
        ]);

        $xml = $this->feed();
        $this->assertSame('2.0', (string) $xml['version']);

        $channel = $xml->channel;
        foreach (['title', 'link', 'description', 'language'] as $tag) {
            $this->assertNotSame('', (string) $channel->{$tag}, "Kanalen saknar {$tag}.");
        }
        $this->assertSame('sv-se', (string) $channel->language);

        $atom = $channel->children('http://www.w3.org/2005/Atom')->link;
        $this->assertSame('self', (string) $atom->attributes()['rel']);
        $this->assertStringEndsWith('/press/feed.xml', (string) $atom->attributes()['href']);

        $item = $this->feedItems()['Press flode'];
        $url = (string) $item->link;
        $this->assertMatchesRegularExpression('#^https?://[^/]+/press/'.self::PREFIX.'flode$#', $url);
        $this->assertSame($url, (string) $item->guid);
        $this->assertSame('true', (string) $item->guid['isPermaLink']);
        $this->assertSame('Sammanfattning för flode', (string) $item->description);
        $this->assertSame('Wed, 04 Mar 2026 09:30:00 +0000', (string) $item->pubDate);
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} [+-]\d{4}$/', (string) $item->pubDate);
        $this->assertSame("Rad ett\nRad två", (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded);
        $this->assertSame("Rad ett\nRad två", (string) $item->children('https://oqto.se/ns/press')->linkedinCaption);
        $this->assertStringContainsString('/images/press/integration.png', (string) $item->enclosure['url']);
        $this->assertStringContainsString('/images/press/integration.png', (string) $item->children('http://search.yahoo.com/mrss/')->content->attributes()['url']);
    }

    public function test_the_linkedin_caption_falls_back_to_the_summary(): void
    {
        $this->item('utan-text');

        $item = $this->feedItems()['Press utan-text'];

        $this->assertSame('Sammanfattning för utan-text', (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded);
    }

    public function test_the_feed_holds_the_twenty_newest_items_newest_first(): void
    {
        for ($i = 1; $i <= 22; $i++) {
            $this->item('mass-'.$i, date: now()->subMinutes(30 - $i));
        }

        $items = $this->feed()->channel->item;

        $this->assertCount(20, $items);

        $dates = [];
        foreach ($items as $item) {
            $dates[] = strtotime((string) $item->pubDate);
        }
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
        $this->assertStringContainsString('mass-22', (string) $items[0]->link);
    }

    public function test_special_characters_in_the_feed_are_escaped(): void
    {
        $this->item('tecken', data: ['title' => 'A & B <i>c</i> "d"', 'linkedin_caption' => 'Text ]]> slut']);

        $titles = array_keys($this->feedItems());
        $this->assertContains('A & B <i>c</i> "d"', $titles);
    }

    public function test_contacts_come_live_from_the_team_record(): void
    {
        $member = $this->teamMember('kontakt', ['role' => 'VD', 'email' => 'vd@example.com', 'phone' => '070-111 22 33', 'press_contact' => true]);
        $hidden = $this->teamMember('ej-kontakt', ['role' => 'Dev', 'press_contact' => false]);
        $this->item('med-kontakt', data: [
            'press_contacts' => [$member->id(), $hidden->id()],
            'custom_contacts' => [['name' => 'Extern Person', 'role' => 'Partner AB', 'email' => 'extern@example.com', 'phone' => '08-123']],
        ]);

        $url = '/press/'.self::PREFIX.'med-kontakt';

        $this->get($url)->assertOk()
            ->assertSee('Presskontakt')
            ->assertSee('Person kontakt')->assertSee('VD')->assertSee('vd@example.com')->assertSee('070-111 22 33')
            ->assertSee('href="tel:0701112233"', false)
            ->assertSee('Extern Person')->assertSee('Partner AB')
            ->assertDontSee('Person ej-kontakt');

        // Ändras numret på teamposten slår det igenom direkt.
        $member->set('phone', '070-999 88 77')->save();
        $this->get($url)->assertSee('070-999 88 77')->assertDontSee('070-111 22 33');

        // Slutar personen vara presskontakt försvinner hen.
        $member->set('press_contact', false)->save();
        $this->get($url)->assertDontSee('Person kontakt')->assertSee('Extern Person');
    }

    public function test_no_contact_block_without_contacts(): void
    {
        $this->item('utan-kontakt');

        $this->get('/press/'.self::PREFIX.'utan-kontakt')->assertOk()->assertDontSee('Presskontakt');
    }

    public function test_the_contact_picker_only_lists_press_contacts(): void
    {
        $yes = $this->teamMember('picker-ja', ['press_contact' => true]);
        $no = $this->teamMember('picker-nej', ['press_contact' => false]);
        $unset = $this->teamMember('picker-saknas');

        $query = Entry::query()->where('collection', 'team');
        (new PressContacts)->apply($query, []);
        $ids = $query->get()->map->id()->all();

        $this->assertContains($yes->id(), $ids);
        $this->assertNotContains($no->id(), $ids);
        $this->assertNotContains($unset->id(), $ids);

        $field = Collection::find('press')->entryBlueprint()->field('press_contacts');
        $this->assertSame(['press_contacts'], $field->config()['query_scopes']);
    }

    public function test_new_items_get_the_default_contacts_preselected(): void
    {
        $default = $this->teamMember('standard', ['press_contact' => true, 'press_default_contact' => true]);
        $notDefault = $this->teamMember('inte-standard', ['press_contact' => true, 'press_default_contact' => false]);
        // Förvald men inte längre presskontakt: ska inte föreslås.
        $stale = $this->teamMember('gammal', ['press_contact' => false, 'press_default_contact' => true]);

        $ids = app(PressCatalog::class)->defaultContactIds();
        $this->assertContains($default->id(), $ids);
        $this->assertNotContains($notDefault->id(), $ids);
        $this->assertNotContains($stale->id(), $ids);

        // Nytt formulär (ingen post): förvalet sätts. Redigering (med post): orört.
        $blueprint = Collection::find('press')->entryBlueprint('pressmeddelande');
        EntryBlueprintFound::dispatch($blueprint);
        $this->assertContains($default->id(), $blueprint->field('press_contacts')->config()['default']);

        $existing = $this->item('befintlig');
        $edit = $existing->blueprint();
        $this->assertNull($edit->field('press_contacts')->config()['default'] ?? null);
    }

    public function test_the_body_cannot_inject_script_or_unsafe_links(): void
    {
        $this->item('farlig', data: [
            'body' => [[
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Klicka här', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
                    ['type' => 'text', 'text' => '<script>alert(2)</script>'],
                ],
            ]],
        ]);

        $html = $this->get('/press/'.self::PREFIX.'farlig')->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)', $html);
    }

    public function test_the_admin_requires_login(): void
    {
        $this->get('/cp/collections/press')->assertRedirect();
        $this->get('/cp/collections/press/entries/create/default')->assertRedirect();
        $this->get('/admin/press')->assertNotFound();
    }
}
