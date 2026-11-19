<?php

namespace App\Services\Press;

use App\Tags\Seo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection as Collections;
use Statamic\Facades\Entry;
use Throwable;

/**
 * Allt som pressrummet (/press, /press/{slug} och RSS-flödet) behöver veta om en post:
 * kategori, omslagsbild (alltid med reservbild), delningstexter, LinkedIn-text och
 * presskontakter. Samma logik används av mallarna och av flödet, så att de aldrig
 * kan visa olika saker.
 */
class PressCatalog
{
    public const COLLECTION = 'press';

    public const CONTAINER = 'press';

    public const TEAM_COLLECTION = 'team';

    public const FEED_LIMIT = 20;

    /** Kategorins värde (sparas på posten) => etikett som visas. */
    public const CATEGORIES = [
        'integration' => 'Integration',
        'team_news' => 'Teamnyheter',
        'product_update' => 'Produktuppdatering',
    ];

    /** Standardbild per kategori, används när posten saknar omslagsbild (se resources/og/press.html). */
    public const DEFAULT_IMAGES = [
        'integration' => '/images/press/integration.png',
        'team_news' => '/images/press/team-news.png',
        'product_update' => '/images/press/product-update.png',
    ];

    /**
     * Publicerade poster (inte utkast, inte framtida datum), nyaste först.
     *
     * @return Collection<int, EntryContract>
     */
    public function published(?string $category = null, ?int $limit = null): Collection
    {
        if (! $this->exists()) {
            return collect();
        }

        $query = Entry::query()
            ->where('collection', self::COLLECTION)
            ->whereStatus('published')
            ->orderBy('date', 'desc');

        if ($category !== null && array_key_exists($category, self::CATEGORIES)) {
            $query->where('category', $category);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Finns pressrummet i Statamics cache? Direkt efter en deploy som lägger till collectionen
     * kan den gamla cachen sakna den. Då ska sajten visa ett tomt pressrum, inte krascha
     * (Collection::count och entry-frågor kastar annars fel på varje sida via sidfoten).
     */
    public function exists(): bool
    {
        try {
            return Collections::findByHandle(self::COLLECTION) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /** Finns minst en publicerad post? Används för länken i sidfoten. */
    public function hasPublished(): bool
    {
        try {
            return $this->exists() && $this->published(null, 1)->isNotEmpty();
        } catch (Throwable) {
            return false;
        }
    }

    public function categoryKey(EntryContract $entry): string
    {
        $category = (string) $entry->get('category');

        return array_key_exists($category, self::CATEGORIES) ? $category : 'integration';
    }

    public function categoryLabel(EntryContract $entry): string
    {
        return self::CATEGORIES[$this->categoryKey($entry)];
    }

    /** Sajtens adress utan avslutande snedstreck (samma regel som {{ seo:origin }}). */
    public function origin(): string
    {
        return app(Seo::class)->origin();
    }

    public function feedUrl(): string
    {
        return $this->origin().'/press/feed.xml';
    }

    public function summary(EntryContract $entry): string
    {
        return $this->plain($entry->get('summary'));
    }

    /** LinkedIn-texten, eller sammanfattningen om ingen skrivits. */
    public function linkedinCaption(EntryContract $entry): string
    {
        $caption = $this->plain($entry->get('linkedin_caption'), keepLineBreaks: true);

        return $caption !== '' ? $caption : $this->summary($entry);
    }

    public function socialTitle(EntryContract $entry): string
    {
        $title = $this->plain($entry->get('social_title'));

        return $title !== '' ? $title : $this->plain($entry->get('title'));
    }

    public function socialDescription(EntryContract $entry): string
    {
        $description = $this->plain($entry->get('social_description'));

        return $description !== '' ? $description : $this->summary($entry);
    }

    /**
     * Omslagsbildens adress, alltid satt: egen bild om den finns på disken, annars
     * kategorins standardbild. Relativ som standard, absolut för flöde och delning.
     */
    public function coverUrl(EntryContract $entry, bool $absolute = false): string
    {
        $url = $this->cover($entry)['url'];

        return $absolute ? $this->origin().$url : $url;
    }

    /**
     * Omslagsbildens adress, filstorlek (byte) och MIME-typ, för enclosure i flödet.
     *
     * @return array{url: string, length: int, type: string}
     */
    public function cover(EntryContract $entry): array
    {
        if ($own = $this->containerFile($entry->get('cover_image'))) {
            return $own;
        }

        $path = self::DEFAULT_IMAGES[$this->categoryKey($entry)];
        $file = public_path($path);

        return [
            'url' => $path,
            'length' => is_file($file) ? (int) filesize($file) : 0,
            'type' => 'image/png',
        ];
    }

    /** Porträttet (för teamnyheter) eller null. */
    public function photoUrl(EntryContract $entry): ?string
    {
        return $this->containerFile($entry->get('person_photo'))['url'] ?? null;
    }

    /**
     * Presskontakterna i den ordning de valts: först teammedlemmarna (aktuella uppgifter
     * hämtas ur teamposten varje gång), sedan de egna kontakterna för just den här posten.
     * Teammedlemmar som inte längre är presskontakter (eller tagits bort) hoppas över.
     *
     * @return array<int, array{name: string, role: ?string, email: ?string, phone: ?string, phone_href: ?string, photo: ?string}>
     */
    public function contacts(EntryContract $entry): array
    {
        $contacts = [];

        foreach (Arr::wrap($entry->get('press_contacts')) as $id) {
            $member = is_string($id) ? Entry::find($id) : null;

            if (! $member || $member->collectionHandle() !== self::TEAM_COLLECTION || ! $member->published() || $member->get('press_contact') !== true) {
                continue;
            }

            $contacts[] = [
                'name' => (string) $member->get('title'),
                'role' => $this->nullable($member->get('role')),
                'email' => $this->nullable($member->get('email')),
                'phone' => $this->nullable($member->get('phone')),
                'phone_href' => $this->phoneHref($member->get('phone')),
                'photo' => $this->teamPhotoUrl($member),
            ];
        }

        foreach (Arr::wrap($entry->get('custom_contacts')) as $custom) {
            $name = $this->plain(Arr::get($custom, 'name'));

            if ($name === '') {
                continue;
            }

            $contacts[] = [
                'name' => $name,
                'role' => $this->nullable(Arr::get($custom, 'role')),
                'email' => $this->nullable(Arr::get($custom, 'email')),
                'phone' => $this->nullable(Arr::get($custom, 'phone')),
                'phone_href' => $this->phoneHref(Arr::get($custom, 'phone')),
                'photo' => null,
            ];
        }

        return $contacts;
    }

    /**
     * Id:n på teammedlemmar som är förvalda presskontakter (och fortfarande presskontakter).
     *
     * @return array<int, string>
     */
    public function defaultContactIds(): array
    {
        return Entry::query()
            ->where('collection', self::TEAM_COLLECTION)
            ->where('press_contact', true)
            ->where('press_default_contact', true)
            ->get()
            ->map->id()
            ->values()
            ->all();
    }

    /**
     * Fil i press- eller teamcontainern utifrån sökvägen på posten. Adressen byggs direkt
     * av sökvägen (utan Statamics cachade bildlista) och filen kontrolleras på disken.
     *
     * @return array{url: string, length: int, type: string}|null
     */
    protected function containerFile(mixed $value, string $container = self::CONTAINER): ?array
    {
        $path = Arr::first(Arr::wrap($value));

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $assets = AssetContainer::find($container);
            $disk = $assets?->disk();

            if (! $assets || ! $disk->exists($path)) {
                return null;
            }

            return [
                'url' => rtrim((string) $assets->url(), '/').'/'.implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/')))),
                'length' => (int) $disk->size($path),
                'type' => (string) ($disk->mimeType($path) ?: 'image/jpeg'),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    protected function teamPhotoUrl(EntryContract $member): ?string
    {
        return $this->containerFile($member->get('image'), 'assets')['url'] ?? null;
    }

    /** Telefonnumret som tel:-länk: bara siffror och ett inledande plus. */
    protected function phoneHref(mixed $value): ?string
    {
        $digits = preg_replace('/(?!^\+)[^\d]/', '', $this->plain($value));

        return $digits === '' ? null : $digits;
    }

    protected function nullable(mixed $value): ?string
    {
        $value = $this->plain($value);

        return $value === '' ? null : $value;
    }

    /** Ren text: inga taggar, inga dubbla blanksteg (radbrytningar behålls om så önskas). */
    protected function plain(mixed $value, bool $keepLineBreaks = false): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($keepLineBreaks) {
            return trim((string) preg_replace("/[ \t]+/", ' ', (string) preg_replace("/\r\n?/", "\n", $text)));
        }

        return Str::squish($text);
    }
}
