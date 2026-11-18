<?php

namespace App\Scopes;

use Statamic\Query\Scopes\Scope;

/**
 * Begränsar väljaren "Presskontakter" i pressmeddelandet till de i teamet som har
 * "Tillgänglig som presskontakt" påslaget (se query_scopes i pressmeddelande.yaml).
 */
class PressContacts extends Scope
{
    public function apply($query, $values)
    {
        $query->where('press_contact', true);
    }
}
