<?php

namespace App\Services\Integrations;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Throwable;

class IntegrationsImporter
{
    protected const COLLECTION = 'integrations';

    protected const DISK = 'integrations_s3';

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
        $items = $this->fetchJson($jsonPath);

        $avatarPaths = $this->indexFilesByGuid('avatars');
        $mediaPaths = $this->indexFilesByGuid('media');

        $existingEntries = Entry::whereCollection(self::COLLECTION)
            ->filter(fn (EntryContract $entry) => filled($entry->get('external_id')))
            ->keyBy(fn (EntryContract $entry) => $entry->get('external_id'));

        $seenExternalIds = [];

        foreach ($items as $item) {
            try {
                $externalId = $item['Id'] ?? null;

                if (! $externalId) {
                    throw new \RuntimeException('Post saknar Id, hoppar över.');
                }

                $seenExternalIds[] = $externalId;

                $this->importItem(
                    $item,
                    $existingEntries->get($externalId),
                    $avatarPaths,
                    $mediaPaths,
                );
            } catch (Throwable $e) {
                $this->summary['failed']++;
                $this->errors[] = sprintf(
                    'Id %s: %s',
                    $item['Id'] ?? 'okänt',
                    $e->getMessage()
                );
            }
        }

        $this->unpublishMissing($existingEntries, $seenExternalIds);

        return [
            'summary' => $this->summary,
            'errors' => $this->errors,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchJson(string $jsonPath): array
    {
        $contents = Storage::disk(self::DISK)->get($jsonPath);

        if ($contents === null) {
            throw new \RuntimeException("Kunde inte hämta {$jsonPath} från S3-disken.");
        }

        $items = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Kunde inte tolka JSON: '.json_last_error_msg());
        }

        return $items ?? [];
    }

    /**
     * Listar en mapp på S3-disken en gång och nycklar filerna på deras
     * GUID-filnamn (utan filändelse), oavsett vilken bildtyp de har.
     *
     * @return array<string, string>
     */
    protected function indexFilesByGuid(string $directory): array
    {
        return collect(Storage::disk(self::DISK)->files($directory))
            ->mapWithKeys(fn (string $path) => [
                pathinfo($path, PATHINFO_FILENAME) => $path,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $avatarPaths
     * @param  array<string, string>  $mediaPaths
     */
    protected function importItem(
        array $item,
        ?EntryContract $entry,
        array $avatarPaths,
        array $mediaPaths,
    ): void {
        $sourceUpdatedAt = $this->resolveSourceTimestamp($item);

        if ($entry && ! $this->hasChangedSince($entry, $sourceUpdatedAt)) {
            $this->summary['unchanged']++;

            return;
        }

        $isNew = ! $entry;

        $entry ??= Entry::make()
            ->collection(self::COLLECTION)
            ->slug($this->uniqueSlug($item['Name'] ?? $item['Id'], $item['Id']));

        $entry->set('title', $item['Name'] ?? $item['Id']);
        $entry->set('external_id', $item['Id']);
        $entry->set('short_description', $item['ShortDescription'] ?? null);
        $entry->set('description', $item['Description'] ?? null);
        $entry->set('reasons', $item['Reasons'] ?? []);
        $entry->set('video_url', $item['VideoUrl'] ?? null);
        $entry->set('developer_name', $item['DeveloperName'] ?? null);
        $entry->set('company_employee_range', $item['CompanyEmployeeRange'] ?? null);
        $entry->set('company_revenue_range', $item['CompanyRevenueRange'] ?? null);
        $entry->set('partner_email', $item['PartnerEmail'] ?? null);
        $entry->set('partner_phone', $item['PartnerPhone'] ?? null);
        $entry->set('partner_website', $item['PartnerWebsite'] ?? null);
        $entry->set('partner_about', $item['PartnerAbout'] ?? null);
        $entry->set('price', $item['Price'] ?? null);

        $entry->set(
            'source_created_at',
            $this->parseDate($item['CreatedAt'] ?? null)
        );
        $entry->set('source_updated_at', $sourceUpdatedAt?->format('Y-m-d H:i'));

        if ($avatarKey = $item['AvatarKey'] ?? null) {
            $entry->set('avatar', array_filter([$avatarPaths[$avatarKey] ?? null]));
        }

        $mediaKeys = collect($item['MediaImageKeys'] ?? [])
            ->map(fn (string $key) => $mediaPaths[$key] ?? null)
            ->filter()
            ->values()
            ->all();

        $entry->set('media', $mediaKeys);

        if ($integrationType = $item['IntegrationType'] ?? null) {
            $entry->set('integration_type', [
                $this->firstOrCreateTerm('integration_type', $integrationType),
            ]);
        }

        $sniCodes = collect($item['SniCodes'] ?? [])
            ->filter()
            ->map(fn (string $code) => $this->firstOrCreateTerm('sni_codes', $code))
            ->values()
            ->all();

        $entry->set('sni_codes', $sniCodes);

        $entry->published(true);
        $entry->save();

        $this->summary[$isNew ? 'created' : 'updated']++;
    }

    protected function resolveSourceTimestamp(array $item): ?Carbon
    {
        return $this->parseDate($item['UpdatedAt'] ?? null)
            ?? $this->parseDate($item['CreatedAt'] ?? null);
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function hasChangedSince(EntryContract $entry, ?Carbon $sourceUpdatedAt): bool
    {
        if (! $sourceUpdatedAt) {
            // Kan inte avgöra ålder - importera hellre en gång för mycket
            // än att aldrig uppdatera posten.
            return true;
        }

        $stored = $this->parseDate($entry->get('source_updated_at'));

        if (! $stored) {
            return true;
        }

        return $sourceUpdatedAt->gt($stored);
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
