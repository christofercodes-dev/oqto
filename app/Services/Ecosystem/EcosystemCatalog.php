<?php

namespace App\Services\Ecosystem;

use App\Modifiers\SignedUrl;
use Illuminate\Support\Collection;
use Throwable;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

/**
 * Ekosystemen under /integrationer/ekosystem visar hur flera integrationer
 * samverkar. Varje ekosystem är en post med de system som ingår och ett eller
 * flera flöden (steg där systemen pratar med varandra). Den här klassen gör om
 * posten till den normaliserade form som både mallarna och det interaktiva
 * diagrammet läser, så att redaktionella misstag (ett steg som pekar på ett
 * system som saknas) aldrig kan krascha sidan.
 */
class EcosystemCatalog
{
    public const COLLECTION = 'ekosystem';

    public const TONES = ['blue', 'yellow', 'peach', 'green', 'purple', 'gray'];

    /**
     * Inbyggda ikoner för system som inte är en integration (banken,
     * leverantören ...). Egna streckade SVG:er som ärver textfärgen. "oqto"
     * är bläckfiskmärket och hanteras som en bild (se systems()).
     */
    public const ICONS = [
        'bank' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10l9-6 9 6"/><path d="M5.5 10v8M9.8 10v8M14.2 10v8M18.5 10v8"/><path d="M3 20.5h18"/></svg>',
        'supplier' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>',
        'document' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/></svg>',
        'person' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7"/></svg>',
    ];

    /** Inloggningsfri bild på Oqtos märke, finns i public/. */
    public const OQTO_MARK = '/favicon.svg';

    /** Hur länge en förhandssignerad bild-URL gäller (minuter). */
    private const LOGO_MINUTES = 240;

    /**
     * Publicerade ekosystem i sorteringsordning.
     *
     * @return Collection<int, EntryContract>
     */
    public function all(): Collection
    {
        return Entry::query()
            ->where('collection', self::COLLECTION)
            ->where('published', true)
            ->get()
            ->sortBy(fn (EntryContract $entry) => sprintf('%05d %s', (int) ($entry->get('order') ?? 99999), $entry->get('title')))
            ->values();
    }

    public function find(string $id): ?EntryContract
    {
        $entry = Entry::find($id);

        return $entry && $entry->collectionHandle() === self::COLLECTION && $entry->published() ? $entry : null;
    }

    /**
     * Ekosystemen där en viss integration ingår som system.
     *
     * @return Collection<int, EntryContract>
     */
    public function containing(string $integrationId): Collection
    {
        return $this->all()->filter(fn (EntryContract $entry) => collect((array) $entry->get('systems'))
            ->contains(fn ($system) => $this->integrationId($system) === $integrationId))
            ->values();
    }

    /**
     * Kortdata för listor: titel, adress, ingress och systemen som ingår.
     *
     * @return array<string, mixed>
     */
    public function card(EntryContract $entry): array
    {
        $systems = $this->systems($entry);

        return [
            'id' => $entry->id(),
            'title' => (string) $entry->get('title'),
            'headline' => (string) ($entry->get('headline') ?: $entry->get('title')),
            'short_description' => (string) $entry->get('short_description'),
            'href' => $entry->url(),
            'systems' => array_values(array_filter($systems, fn (array $s) => $s['used'])),
        ];
    }

    /**
     * Hela ekosystemet, så som sidan och diagrammet läser det.
     *
     * @return array{systems: array<int, array<string, mixed>>, flows: array<int, array<string, mixed>>, document: array<string, string>}
     */
    public function payload(EntryContract $entry): array
    {
        $systems = $this->systems($entry);
        $known = collect($systems)->pluck('key')->all();

        $flows = [];

        foreach ((array) $entry->get('flows') as $flow) {
            if (($flow['enabled'] ?? true) === false) {
                continue;
            }

            $steps = [];

            foreach ((array) ($flow['steps'] ?? []) as $step) {
                $system = $this->key($step['system'] ?? '');

                if (! in_array($system, $known, true)) {
                    continue;
                }

                $with = $this->key($step['with'] ?? '');

                $steps[] = [
                    'n' => count($steps) + 1,
                    'system' => $system,
                    'with' => in_array($with, $known, true) && $with !== $system ? $with : null,
                    'title' => trim((string) ($step['title'] ?? '')),
                    'short' => trim((string) ($step['short'] ?? '')),
                    'text' => trim((string) ($step['text'] ?? '')),
                    'log' => trim((string) ($step['log'] ?? '')),
                    'status' => trim((string) ($step['status'] ?? '')),
                ];
            }

            if ($steps === []) {
                continue;
            }

            $flows[] = [
                'index' => count($flows),
                'title' => trim((string) ($flow['title'] ?? '')) ?: 'Flöde '.(count($flows) + 1),
                'steps' => $steps,
            ];
        }

        return [
            'systems' => $systems,
            'flows' => $flows,
            'document' => [
                'label' => (string) $entry->get('doc_label'),
                'title' => (string) $entry->get('doc_title'),
                'subtitle' => (string) $entry->get('doc_subtitle'),
                'amount_label' => (string) ($entry->get('doc_amount_label') ?: 'Att betala'),
                'amount' => (string) $entry->get('doc_amount'),
            ],
        ];
    }

    /**
     * Systemen med ton, bokstav, ev. länk till integrationssidan och om något
     * flöde faktiskt använder dem (så att ett oanvänt system inte ritas ut).
     *
     * @return array<int, array<string, mixed>>
     */
    private function systems(EntryContract $entry): array
    {
        $used = [];

        foreach ((array) $entry->get('flows') as $flow) {
            foreach ((array) ($flow['steps'] ?? []) as $step) {
                $used[$this->key($step['system'] ?? '')] = true;
                $used[$this->key($step['with'] ?? '')] = true;
            }
        }

        $systems = [];

        foreach ((array) $entry->get('systems') as $system) {
            $key = $this->key($system['key'] ?? '');
            $name = trim((string) ($system['name'] ?? ''));

            if ($key === '' || $name === '' || isset($systems[$key])) {
                continue;
            }

            $integration = ($id = $this->integrationId($system)) ? Entry::find($id) : null;
            $integration = $integration && $integration->published() ? $integration : null;
            $tone = in_array($system['tone'] ?? null, self::TONES, true) ? $system['tone'] : 'gray';

            // Ikonen: valt val först, annars integrationens logga, annars bokstaven.
            $icon = (string) ($system['icon'] ?? '');
            $logo = null;
            $fit = 'cover';
            $svg = null;

            if ($icon === 'oqto') {
                $logo = self::OQTO_MARK;
                $fit = 'contain';
            } elseif (isset(self::ICONS[$icon])) {
                $svg = self::ICONS[$icon];
            } elseif ($integration) {
                $logo = $this->logoUrl($integration);
            }

            $systems[$key] = [
                'key' => $key,
                'name' => $name,
                'letter' => mb_strtoupper(mb_substr($name, 0, 1)),
                'role' => trim((string) ($system['role'] ?? '')),
                'tone' => $tone,
                'x' => $this->percent($system['pos_x'] ?? null),
                'y' => $this->percent($system['pos_y'] ?? null),
                'logo' => $logo,
                'logo_fit' => $fit,
                'icon_svg' => $svg,
                'href' => $integration ? $integration->url() : null,
                'used' => isset($used[$key]),
            ];
        }

        return array_values($systems);
    }

    /**
     * Integrationens logga som en tillfälligt signerad URL (bucketen är privat,
     * se App\Modifiers\SignedUrl). Saknas bilden eller går det inte att signera
     * visas bokstaven i stället, sidan påverkas aldrig.
     */
    private function logoUrl(EntryContract $integration): ?string
    {
        $path = collect((array) $integration->get('avatar'))->filter()->first();

        if (! $path) {
            return null;
        }

        try {
            $url = (new SignedUrl)->index('integrations::'.$path, [self::LOGO_MINUTES], []);
        } catch (Throwable) {
            return null;
        }

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function integrationId(mixed $system): ?string
    {
        $value = is_array($system) ? ($system['integration'] ?? null) : null;
        $id = is_array($value) ? ($value[0] ?? null) : $value;

        return $id ? (string) $id : null;
    }

    private function key(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function percent(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, min(100, (int) $value)) : null;
    }
}
