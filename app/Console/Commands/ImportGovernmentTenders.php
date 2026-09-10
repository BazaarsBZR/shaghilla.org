<?php

namespace App\Console\Commands;

use App\Services\PublicMoney\PpaTenderImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportGovernmentTenders extends Command
{
    protected $signature = 'government-tenders:import
        {--all-pages : Traverse every current PPA listing page}
        {--pages=3 : Listing pages to refresh during a bounded run}
        {--details=18 : Detail pages to hydrate during this run}
        {--time-budget=300 : Maximum runtime in seconds}';

    protected $description = 'Import official Lebanese Public Procurement Authority tender announcements';

    public function handle(PpaTenderImporter $importer): int
    {
        try {
            $result = $importer->import(
                pageLimit: max(1, (int) $this->option('pages')),
                detailLimit: max(0, (int) $this->option('details')),
                allPages: (bool) $this->option('all-pages'),
                timeBudgetSeconds: max(15, (int) $this->option('time-budget')),
            );
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
