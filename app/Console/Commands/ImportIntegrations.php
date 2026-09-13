<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationsImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportIntegrations extends Command
{
    protected $signature = 'integrations:import';

    protected $description = 'Importerar/synkar integrationer från S3-bucketen (integrations.json + bilder).';

    public function handle(IntegrationsImporter $importer): int
    {
        $jsonPath = config('services.integrations_s3.json_path', 'integrations.json');

        $this->info("Importerar integrationer från \"{$jsonPath}\"...");

        try {
            $result = $importer->run($jsonPath);
        } catch (Throwable $e) {
            $this->error('Importen misslyckades: '.$e->getMessage());
            Log::error('Integrations-import misslyckades: '.$e->getMessage(), ['exception' => $e]);

            return self::FAILURE;
        }

        $summary = $result['summary'];

        $message = sprintf(
            'Klart. Skapade: %d, uppdaterade: %d, oförändrade: %d, avpublicerade: %d, fel: %d.',
            $summary['created'],
            $summary['updated'],
            $summary['unchanged'],
            $summary['unpublished'],
            $summary['failed'],
        );

        $this->info($message);
        Log::info('Integrations-import: '.$message);

        foreach ($result['errors'] as $error) {
            $this->warn($error);
            Log::warning('Integrations-import: '.$error);
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
