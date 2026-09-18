<?php

namespace App\Services\Integrations;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Statamic\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Throwable;

/**
 * Importerar/synkar "applications.json" (verkligt fältformat, bekräftat
 * 2026-09-15 - se README för bakgrund) från S3-disken "integrations_s3".
 *
 * Filen har formen {"generatedAt": "...", "applications": [ {..camelCase
 * fält..} ]}. Källan saknar ett per-post "updatedAt" - bara "createdAt".
 * Eftersom hela datamängden är liten (~50 poster) används därför en enkel
 * strategi: hoppa över hela körningen om filens "generatedAt" inte ändrats
 * sedan senast, annars skriv om samtliga poster (billigt i den här skalan,
 * och undviker att behöva gissa vilka enskilda poster som ändrats).
 *
 * avatarKey/mediaImageKeys innehåller redan fullständiga, klara sökvägar
 * (t.ex. "images/<guid>.png") relativt disken - ingen fillistning eller
 * GUID-uppslagning behövs, vilket också innebär att importen inte kräver
 * s3:ListBucket, bara s3:GetObject.
 */
class IntegrationsImporter
{
    protected const COLLECTION = 'integrations';

    protected const DISK = 'integrations_s3';

    protected const CACHE_KEY = 'integrations_import.last_generated_at';

    /**
     * @var array<string, int>
     */
    protected array $summary = [
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'unpublished' => 0,
        'failed' => 0,
    ];

    /**
     * @var array<int, string>
     */
    protected array $errors = [];

    /**
     * @return array{summary: array<string, int>, errors: array<int, string>}
     */
    public function run(string $jsonPath): array
    {
        [$generatedAt, $items] = $this->fetchJson($jsonPath);

        if ($generatedAt && $generatedAt === Cache::get(self::CACHE_KEY)) {
            return [
                'summary' => $this->summary,
                'errors' => ['Ingen ny export sedan senaste körningen (generatedAt oförändrat) - hoppar över.'],
            ];
        }

        $existingEntries = Entry::whereCollection(self::COLLECTION)
            ->filter(fn (EntryContract $entry) => filled($entry->get('external_id')))
            ->keyBy(fn (EntryContract $entry) => $entry->get('external_id'));

        $seenExternalIds = [];

        foreach ($items as $item) {
            try {
                $externalId = $item['id'] ?? null;

                if (! $externalId) {
                    throw new RuntimeException('Post saknar id, hoppar över.');
                }

                $seenExternalIds[] = $externalId;

                $this->importItem($item, $existingEntries->get($externalId));
            } catch (Throwable $e) {
                $this->summary['failed']++;
                $this->errors[] = sprintf(
                    'Id %s: %s',
                    $item['id'] ?? 'okänt',
                    $e->getMessage()
                );
            }
        }

        $this->unpublishMissing($existingEntries, $seenExternalIds);

        if ($generatedAt && $this->summary['failed'] === 0) {
            Cache::forever(self::CACHE_KEY, $generatedAt);
        }

        return [
            'summary' => $this->summary,
            'errors' => $this->errors,
        ];
    }

    /**
     * @return array{0: ?string, 1: array<int, array<string, mixed>>}
     */
    protected function fetchJson(string $jsonPath): array
    {
        $contents = Storage::disk(self::DISK)->get($jsonPath);

        if ($contents === null) {
            throw new RuntimeException("Kunde inte hämta {$jsonPath} från S3-disken.");
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Kunde inte tolka JSON: '.json_last_error_msg());
        }

        return [
            $decoded['generatedAt'] ?? null,
            $decoded['applications'] ?? [],
        ];
    }

    protected function importItem(array $item, ?EntryContract $entry): void
    {
        $isNew = ! $entry;

        $entry ??= Entry::make()
            ->collection(self::COLLECTION)
            ->slug($this->uniqueSlug($this->trimmed($item['name'] ?? $item['id']), $item['id']));

        $entry->set('title', $this->trimmed($item['name'] ?? $item['id']));
        $entry->set('external_id', $item['id']);
        $entry->set('short_description', $this->trimmed($item['shortDescription'] ?? null));
        $entry->set('description', $this->trimmed($item['description'] ?? null));
        $entry->set('reasons', collect($item['reasons'] ?? [])->map(fn ($reason) => $this->trimmed($reason))->filter()->values()->all());
        $entry->set('developer_name', $this->trimmed($item['developerName'] ?? null));
        $entry->set('company_employee_range', $this->trimmed($item['companyEmployeeRange'] ?? null));
        $entry->set('company_revenue_range', $this->trimmed($item['companyRevenueRange'] ?? null));
        $entry->set('partner_email', $this->trimmed($item['partnerEmail'] ?? null));
        $entry->set('partner_phone', $this->trimmed($item['partnerPhone'] ?? null));
        $entry->set('partner_website', $this->trimmed($item['partnerWebsite'] ?? null));
        $entry->set('partner_about', $this->trimmed($item['partnerAbout'] ?? null));
        $entry->set('price', $this->trimmed($item['price'] ?? null));
        $entry->set('source_created_at', $this->parseDate($item['createdAt'] ?? null));

        // avatarKey/mediaImageKeys är redan kompletta sökvägar
        // (t.ex. "images/<guid>.png") relativt disken - används rakt av.
        if ($avatarKey = $item['avatarKey'] ?? null) {
            $entry->set('avatar', [$avatarKey]);
        }

        $entry->set('media', array_values(array_filter($item['mediaImageKeys'] ?? [])));

        // Källdatan representerar varje SNI-kod som ett objekt
        // {"code": "...", "name": "..."} - inte en ren sträng. Vi bryr oss
        // bara om koden, för att matcha hur befintliga sni_codes-termer
        // redan är namngivna (title = koden, t.ex. "69").
        $sniCodes = collect($item['sniCodes'] ?? [])
            ->map(fn ($code) => is_array($code) ? ($code['code'] ?? null) : $code)
            ->filter()
            ->map(fn (string $code) => $this->firstOrCreateTerm('sni_codes', $code))
            ->values()
            ->all();

        $entry->set('sni_codes', $sniCodes);

        $entry->published(true);
        $entry->save();

        $this->summary[$isNew ? 'created' : 'updated']++;
    }

    /**
     * Källdatan (applications.json) innehåller enstaka fält med extra
     * inledande/avslutande whitespace (t.ex. " Nets Easy Pro "), vilket
     * annars gör att sådana poster hamnar först vid alfabetisk sortering
     * (mellanslag sorterar före bokstäver) trots att de ser normala ut.
     */
    protected function trimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (Throwable) {
            return null;
        }
    }

    protected function firstOrCreateTerm(string $taxonomy, string $value): string
    {
        $slug = Str::slug($value);

        $term = Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('slug', $slug)
            ->first();

        if (! $term) {
            $term = Term::make()
                ->taxonomy($taxonomy)
                ->slug($slug)
                ->data(['title' => $value]);

            $term->save();
        }

        return $slug;
    }

    protected function uniqueSlug(string $name, string $externalId): string
    {
        $base = Str::slug($name) ?: Str::slug($externalId);

        $collides = Entry::whereCollection(self::COLLECTION)
            ->contains(fn (EntryContract $entry) => $entry->slug() === $base);

        if (! $collides) {
            return $base;
        }

        return $base.'-'.Str::substr($externalId, 0, 8);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, EntryContract>  $existingEntries
     * @param  array<int, string>  $seenExternalIds
     */
    protected function unpublishMissing($existingEntries, array $seenExternalIds): void
    {
        $existingEntries
            ->except($seenExternalIds)
            ->each(function (EntryContract $entry) {
                if (! $entry->published()) {
                    return;
                }

                $entry->published(false);
                $entry->save();

                $this->summary['unpublished']++;
            });
    }
}
