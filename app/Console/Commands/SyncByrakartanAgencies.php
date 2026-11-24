<?php

namespace App\Console\Commands;

use App\Services\Byrakartan\AgencyDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncByrakartanAgencies extends Command
{
    protected $signature = 'byrakartan:sync';

    protected $description = 'Hämtar byråerna från Byråkartans API och uppdaterar cachen bakom /byrakartan.';

    public function handle(AgencyDirectory $directory): int
    {
        try {
            $agencies = $directory->refresh();
        } catch (Throwable $e) {
            $this->error('Synkningen misslyckades: '.$e->getMessage());
            Log::warning('Byråkartan-synk misslyckades: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Klart. %d byråer.', count($agencies)));

        return self::SUCCESS;
    }
}
