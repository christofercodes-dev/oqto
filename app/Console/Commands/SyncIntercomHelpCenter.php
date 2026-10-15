<?php

namespace App\Console\Commands;

use App\Services\Intercom\HelpCenter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncIntercomHelpCenter extends Command
{
    protected $signature = 'intercom:sync-help-center';

    protected $description = 'Hämtar hjälpartiklarna från Intercom och uppdaterar cachen bakom /support.';

    public function handle(HelpCenter $helpCenter): int
    {
        $this->info('Hämtar hjälpcentret från Intercom...');

        try {
            $data = $helpCenter->refresh();
        } catch (Throwable $e) {
            $this->error('Synkningen misslyckades: '.$e->getMessage());
            Log::error('Intercom-synk misslyckades: '.$e->getMessage(), ['exception' => $e]);

            return self::FAILURE;
        }

        $message = sprintf('Klart. %d kategorier, %d artiklar.', count($data['categories']), count($data['articles']));

        $this->info($message);
        Log::info('Intercom-synk: '.$message);

        return self::SUCCESS;
    }
}
