<?php

namespace App\Console\Commands;

use App\Services\PublicMoney\PublicMoneyImporter;
use Illuminate\Console\Command;

class ImportPublicMoney extends Command
{
    protected $signature = 'public-money:import {--source=} {--limit=50} {--publish-verified : Publish this verified initial import}';

    protected $description = 'Import official Lebanese procurement and public-finance records';

    public function handle(PublicMoneyImporter $importer): int
    {
        $results = $importer->import($this->option('source') ?: null, (int) $this->option('limit'), (bool) $this->option('publish-verified'));
        $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return collect($results)->contains(fn (array $result): bool => $result['status'] === 'failed') ? self::FAILURE : self::SUCCESS;
    }
}
