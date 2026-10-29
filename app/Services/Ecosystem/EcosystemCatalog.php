<?php

namespace App\Services\Ecosystem;

use Illuminate\Support\Collection;
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
            $tone = in_array($system['tone'] ?? null, self::TONES, true) ? $system['tone'] : 'gray';

            $systems[$key] = [
                'key' => $key,
                'name' => $name,
                'letter' => mb_strtoupper(mb_substr($name, 0, 1)),
                'role' => trim((string) ($system['role'] ?? '')),
                'tone' => $tone,
                'x' => $this->percent($system['pos_x'] ?? null),
                'y' => $this->percent($system['pos_y'] ?? null),
                'href' => $integration && $integration->published() ? $integration->url() : null,
                'used' => isset($used[$key]),
            ];
        }

        return array_values($systems);
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
