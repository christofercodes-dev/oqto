<?php

namespace App\Tags;

use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Statamic\Tags\Tags;

/**
 * Hjälptaggar för /integrationer-sidan. "developer_name" är det enda fält
 * som är konsekvent ifyllt över hela den S3-importerade integrations-
 * collectionen (till skillnad från t.ex. sni_codes, som bara finns på ett
 * fåtal poster) - det används därför som filter. Statamics inbyggda taggar
 * har inget bra sätt att räkna fram unika värden för ett vanligt textfält
 * (till skillnad från en taxonomy), därför den egna taggen.
 */
class Integrations extends Tags
{
    protected static $handle = 'integrations';

    /**
     * {{ integrations:developers }} {{ name }} ({{ slug }}) {{ /integrations:developers }}
     * Unika utvecklarnamn, sorterade i bokstavsordning, med tillhörande
     * slug att använda som filter-nyckel (matchar :signed_url-modiferns
     * slugify av samma fält på varje kort).
     */
    public function developers()
    {
        return Entry::query()
            ->where('collection', 'integrations')
            ->get()
            ->map(fn ($entry) => trim((string) $entry->get('developer_name')))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($name) => [
                'name' => $name,
                'slug' => Str::slug($name),
            ]);
    }
}
