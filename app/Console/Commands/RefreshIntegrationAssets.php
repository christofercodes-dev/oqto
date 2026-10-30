<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationsImporter;
use Illuminate\Console\Command;

class RefreshIntegrationAssets extends Command
{
    protected $signature = 'integrations:refresh-assets';

    protected $description = 'Bygger om listan över bilder i integrationernas bucket, så att nya loggor syns.';

    public function handle(IntegrationsImporter $importer): int
    {
        $importer->refreshAssetListing();

        $this->info('Listan över integrationernas bilder är ombyggd.');

        return self::SUCCESS;
    }
}
