<?php

namespace App\Scopes;

use App\Services\Integrations\IntegrationLogos;
use Statamic\Query\Scopes\Filter;

/**
 * Filter i integrationslistan i kontrollpanelen: visar de integrationer som
 * saknar logotyp (varken från källan eller uppladdad), så att nattens nya
 * integrationer utan logotyp hittas direkt och kan få en under "Egen logotyp".
 */
class Logo extends Filter
{
    public static function title()
    {
        return 'Logotyp';
    }

    public function fieldItems()
    {
        return [
            'logo' => [
                'type' => 'select',
                'placeholder' => 'Logotyp',
                'options' => [
                    'saknas' => 'Saknas',
                    'finns' => 'Finns',
                ],
            ],
        ];
    }

    public function apply($query, $values)
    {
        $missing = IntegrationLogos::missing()->map->id()->all();

        if (($values['logo'] ?? null) === 'saknas') {
            $query->whereIn('id', $missing);
        } else {
            $query->whereNotIn('id', $missing);
        }
    }

    public function badge($values)
    {
        return 'Logotyp: '.(($values['logo'] ?? null) === 'saknas' ? 'saknas' : 'finns');
    }

    public function visibleTo($key)
    {
        return $key === 'entries' && ($this->context['collection'] ?? null) === IntegrationLogos::COLLECTION;
    }
}
