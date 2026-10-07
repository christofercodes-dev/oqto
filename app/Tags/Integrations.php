<?php

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Tags\Tags;

/**
 * Hjälptaggar för filtren på /integrationer-sidan. Alternativen räknas
 * fram ur de importerade integrationerna så att nya branscher eller
 * intervall från källan dyker upp i filtren utan att mallen ändras.
 */
class Integrations extends Tags
{
    protected static $handle = 'integrations';

    /**
     * Termen för "gäller alla branscher" (SNI-koden "*" i källdatan) - ska
     * inte vara ett eget filterval, utan matchar alla valda branscher.
     */
    protected const ALL_INDUSTRIES_SLUG = 'branschoberoende';

    /**
     * {{ integrations:industries }} {{ name }} ({{ slug }}) {{ /integrations:industries }}
     * Branscher (SNI) med klartext från källan, i bokstavsordning.
     */
    public function industries()
    {
        return Term::query()
            ->where('taxonomy', 'sni_codes')
            ->get()
            ->reject(fn ($term) => $term->slug() === self::ALL_INDUSTRIES_SLUG)
            ->map(fn ($term) => [
                'name' => (string) ($term->get('description') ?: $term->get('title')),
                'slug' => $term->slug(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * {{ integrations:employee_sizes }} {{ label }} ({{ value }}) {{ /integrations:employee_sizes }}
     */
    public function employeeSizes()
    {
        return $this->ranges('company_employee_ranges')->map(fn ($value) => [
            'value' => $value,
            'label' => $value === '0'
                ? 'Inga anställda'
                : str_replace('-', '–', $value).' anställda',
        ]);
    }

    /**
     * {{ integrations:revenues }} {{ label }} ({{ value }}) {{ /integrations:revenues }}
     * Omsättningsintervallen anges i miljoner kronor (Mkr).
     */
    public function revenues()
    {
        return $this->ranges('company_revenue_ranges')->map(fn ($value) => [
            'value' => $value,
            'label' => str_replace('-', '–', $value).' Mkr',
        ]);
    }

    /**
     * Unika intervall för ett fält, sorterade efter nedre gränsen
     * (0-1, 1-3, 3-10 ... 250+).
     */
    protected function ranges(string $field)
    {
        return Entry::query()
            ->where('collection', 'integrations')
            ->get()
            ->flatMap(fn ($entry) => (array) $entry->get($field))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->sortBy(fn ($value) => (int) $value)
            ->values();
    }
}
