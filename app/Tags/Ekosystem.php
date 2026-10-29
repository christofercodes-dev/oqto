<?php

namespace App\Tags;

use App\Services\Ecosystem\EcosystemCatalog;
use Statamic\Tags\Tags;

/**
 * Hjälptaggar för sidorna under /integrationer/ekosystem.
 */
class Ekosystem extends Tags
{
    protected static $handle = 'ekosystem';

    /**
     * {{ ekosystem:list as="ekosystem" }} ... {{ /ekosystem:list }}
     * Alla publicerade ekosystem som kort (title, headline, short_description,
     * href och systems). Med integration="id" bara de där integrationen ingår,
     * med limit="n" högst n stycken.
     */
    public function list()
    {
        $catalog = app(EcosystemCatalog::class);

        $integration = $this->params->get('integration');

        $entries = $integration ? $catalog->containing((string) $integration) : $catalog->all();

        if ($limit = (int) $this->params->get('limit')) {
            $entries = $entries->take($limit);
        }

        return $this->aliasedResult($entries->map(fn ($entry) => $catalog->card($entry))->all());
    }

    /**
     * {{ ekosystem:flows as="flows" }} ... {{ /ekosystem:flows }}
     * Flödena i ekosystemet som visas just nu, med steg och systemens namn.
     * Används för den statiska versionen som syns utan JavaScript.
     */
    public function flows()
    {
        $payload = $this->payload();
        $names = collect($payload['systems'] ?? [])->keyBy('key');

        $flows = collect($payload['flows'] ?? [])->map(function (array $flow) use ($names) {
            $flow['steps'] = array_map(function (array $step) use ($names) {
                $step['system_name'] = $names[$step['system']]['name'] ?? '';

                return $step;
            }, $flow['steps']);

            return $flow;
        })->all();

        return $this->aliasedResult($flows);
    }

    /**
     * {{ ekosystem:systems as="systems" }} ... {{ /ekosystem:systems }}
     * Systemen som ingår i flödena, med href till integrationssidan när det finns en.
     */
    public function systems()
    {
        $systems = array_values(array_filter($this->payload()['systems'] ?? [], fn (array $s) => $s['used']));

        return $this->aliasedResult($systems);
    }

    /**
     * {{ ekosystem:json }}
     * Hela ekosystemet som JSON för det interaktiva diagrammet. Tecknen som
     * kan stänga ett script-element eller starta en kommentar kodas om.
     */
    public function json(): string
    {
        return json_encode(
            $this->payload(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    private function payload(): array
    {
        $catalog = app(EcosystemCatalog::class);

        $id = $this->params->get('id') ?? $this->context->get('id');
        $entry = $id ? $catalog->find((string) $id) : null;

        return $entry ? $catalog->payload($entry) : ['systems' => [], 'flows' => [], 'document' => []];
    }
}
