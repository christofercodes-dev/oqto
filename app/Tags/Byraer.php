<?php

namespace App\Tags;

use App\Services\Byrakartan\AgencyDirectory;
use Statamic\Tags\Tags;

/**
 * {{ byraer:has_items }}: finns det byråer att visa på /byrakartan? Läser bara ur
 * cachen (färsk eller senast kända) och anropar aldrig Byråkartans API, så att
 * sidfoten på varje sida inte väntar på nätverket.
 */
class Byraer extends Tags
{
    protected static $handle = 'byraer';

    public function hasItems(): bool
    {
        try {
            return app(AgencyDirectory::class)->cached() !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
