<?php

namespace App\Tags;

use App\Services\Press\PressCatalog;
use Statamic\Tags\Tags;

/**
 * Hjälptagg för sidfoten: {{ if {press:has_items} }} ... {{ /if }}.
 * Kastar aldrig fel, även om Statamics cache är föråldrad efter en deploy.
 */
class Press extends Tags
{
    protected static $handle = 'press';

    /** {{ press:has_items }} - finns minst en publicerad pressmeddelande? */
    public function hasItems(): bool
    {
        return app(PressCatalog::class)->hasPublished();
    }
}
