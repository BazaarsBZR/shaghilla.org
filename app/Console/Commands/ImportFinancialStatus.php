<?php

namespace App\Console\Commands;

use App\Services\PublicMoney\FinancialStatusImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportFinancialStatus extends Command
{
    protected $signature = 'financial-status:import';

    protected $description = 'Discover and import verified Lebanese Ministry of Finance indicators';

    public function handle(FinancialStatusImporter $importer): int
    {
        try {
            $this->line(json_encode($importer->import(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
