<?php

namespace App\Services\PublicMoney;

use App\Models\FinancialObservation;
use App\Models\ProcurementRecord;
use App\Models\PublicMoneyDocument;
use App\Models\PublicMoneyImportRun;
use App\Models\PublicMoneyReviewAction;
use App\Models\PublicMoneySource;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Smalot\PdfParser\Parser;

final class PublicMoneyImporter
{
    public function __construct(private readonly OfficialSourceClient $client) {}

    /** @return array<string, array<string, int|string|null>> */
    public function import(?string $onlySource = null, int $limit = 50, bool $publishVerified = false): array
    {
        $lock = Cache::lock('public-money-import', 840);
        if (! $lock->get()) {
            throw new RuntimeException('A public-money import is already running.');
        }

        try {
            $sources = PublicMoneySource::query()->where('is_enabled', true)
                ->when($onlySource, fn ($query) => $query->where('key', $onlySource))
                ->orderBy('id')->get();
            $results = [];
            foreach ($sources as $source) {
                $results[$source->key] = $this->importSource($source, max(1, min(200, $limit)), $publishVerified);
            }
            return $results;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, int|string|null> */
    private function importSource(PublicMoneySource $source, int $limit, bool $publishVerified): array
    {
        $run = PublicMoneyImportRun::create(['source_id' => $source->id, 'status' => 'running', 'started_at' => now()]);
        $counts = ['discovered_count' => 0, 'created_count' => 0, 'updated_count' => 0, 'failed_count' => 0];
        $source->update(['last_checked_at' => now()]);

        try {
            $counts = str_starts_with($source->adapter, 'ppa_')
                ? $this->importPpa($source, $run, $limit, $publishVerified)
                : $this->importMof($source, $run, $publishVerified);
            $run->update([...$counts, 'status' => $counts['failed_count'] ? 'partial' : 'succeeded', 'finished_at' => now()]);
            $source->update(['last_success_at' => now(), 'last_error' => null]);
        } catch (\Throwable $error) {
            $counts['failed_count']++;
            $run->update([...$counts, 'status' => 'failed', 'finished_at' => now(), 'error_summary' => mb_substr($error->getMessage(), 0, 2000)]);
            $source->update(['last_error_at' => now(), 'last_error' => mb_substr($error->getMessage(), 0, 2000)]);
        }

        return [...$counts, 'status' => $run->fresh()->status, 'error' => $run->fresh()->error_summary];
    }

    /** @return array{discovered_count:int,created_count:int,updated_count:int,failed_count:int} */
    private function importPpa(PublicMoneySource $source, PublicMoneyImportRun $run, int $limit, bool $publishVerified): array
    {
        $download = $this->client->get($source->discovery_url);
        $document = $this->document($source, $run, $download, strtoupper(str_replace('_', ' ', $source->adapter)), 'html');
        $rows = array_slice($this->parsePpaTable($download['body'], $source->adapter, $download['url']), 0, $limit);
        $counts = ['discovered_count' => count($rows), 'created_count' => 0, 'updated_count' => 0, 'failed_count' => 0];
        foreach ($rows as $row) {
            try {
                $row['source_id'] = $source->id;
                $row['document_id'] = $document->id;
                $result = $this->upsertProcurement($row, $publishVerified);
                $counts[$result.'_count']++;
            } catch (\Throwable) {
                $counts['failed_count']++;
            }
        }
        $document->update(['extraction_status' => $counts['failed_count'] ? 'partial' : 'parsed']);
        return $counts;
    }

    /** @return array<int, array<string, mixed>> */
    public function parsePpaTable(string $html, string $adapter, string $pageUrl): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $stage = str_replace('ppa_', '', $adapter);
        $items = [];
        foreach ($xpath->query('//table//tbody/tr') ?: [] as $tr) {
            $cells = [];
            foreach ($xpath->query('./td', $tr) ?: [] as $td) {
                $cells[] = $this->clean($td->textContent);
            }
            if (count($cells) < 4) {
                continue;
            }
            $link = null;
            foreach ($xpath->query('.//a[@href]', $tr) ?: [] as $a) {
                if ($a instanceof DOMElement && str_contains($a->getAttribute('href'), '/tenders/details/')) {
                    $link = $a->getAttribute('href');
                }
            }
            $sourceUrl = $link ? $this->absolutePpaUrl($link) : $pageUrl;
            preg_match('~/tenders/details/([^/]+)~', $sourceUrl, $idMatch);
            $sourceId = $idMatch[1] ?? hash('sha256', implode('|', $cells));

            if ($stage === 'awards') {
                $money = MoneyParser::parse($cells[5] ?? null);
                $items[] = $this->procurementPayload($sourceId, 'award', $cells[1], $cells[0], $cells[4] ?: null, $money, $cells[2] ?? null, $sourceUrl, ['standstill_end_on' => $this->date($cells[3] ?? null)]);
            } elseif ($stage === 'contracts') {
                $items[] = $this->procurementPayload($sourceId, 'contract', $cells[1], $cells[0], $cells[3] ?: null, MoneyParser::parse(null), $cells[2] ?? null, $sourceUrl, ['contract_on' => $this->date($cells[2] ?? null)]);
            } else {
                $items[] = $this->procurementPayload($sourceId, 'implementation', $cells[2] ?? $cells[1], $cells[1] ?? $cells[0], null, MoneyParser::parse(null), $cells[4] ?? null, $sourceUrl, ['description' => $cells[3] ?? null, 'implementation_on' => $this->date($cells[4] ?? null)]);
            }
        }
        return $items;
    }

    /** @return array<string, mixed> */
    private function procurementPayload(string $id, string $stage, string $title, string $authority, ?string $supplier, array $money, ?string $date, string $url, array $extra = []): array
    {
        return [...$extra, 'external_key' => $stage.':'.$id, 'source_record_id' => $id, 'procurement_id' => $id, 'stage' => $stage, 'original_stage' => $stage, 'status_normalized' => 'active', 'title' => $title, 'authority' => $authority, 'supplier' => $supplier, 'amount' => $money['amount'], 'amount_text' => $money['original'], 'currency' => $money['currency'], 'event_on' => $this->date($date), 'publication_on' => $stage === 'award' ? $this->date($date) : null, 'source_url' => $url, 'evidence' => ['source_page' => $url, 'extraction' => 'official_html_table']];
    }

    /** @return array{discovered_count:int,created_count:int,updated_count:int,failed_count:int} */
    private function importMof(PublicMoneySource $source, PublicMoneyImportRun $run, bool $publishVerified): array
    {
        $download = $this->client->get($source->discovery_url);
        $document = $this->document($source, $run, $download, $source->name_en, 'pdf');
        $text = (new Parser())->parseContent($download['body'])->getText();
        $rows = $source->adapter === 'mof_budget_2026'
            ? $this->budget2026Rows($text, $download['url'])
            : $this->finance2025Rows($text, $download['url']);
        $counts = ['discovered_count' => count($rows), 'created_count' => 0, 'updated_count' => 0, 'failed_count' => 0];
        foreach ($rows as $row) {
            try {
                $row['source_id'] = $source->id;
                $row['document_id'] = $document->id;
                $result = $this->upsertFinancial($row, $publishVerified);
                $counts[$result.'_count']++;
            } catch (\Throwable) {
                $counts['failed_count']++;
            }
        }
        $document->update(['extraction_status' => $counts['failed_count'] ? 'partial' : 'parsed', 'metadata' => ['text_hash' => hash('sha256', $text), 'parser' => 'smalot/pdfparser']]);
        return $counts;
    }

    /** @return array<int, array<string, mixed>> */
    public function budget2026Rows(string $text, string $url): array
    {
        if (! str_contains(str_replace(["\xc2\xa0", ' '], '', $text), '538,415') && ! str_contains($text, '538 415')) {
            throw new RuntimeException('The 2026 citizen budget total anchor was not found; source format may have changed.');
        }
        $rows = [
            ['total-expenditure', 'Total estimated expenditure', 538415, true, 'p. 6 and p. 25'],
            ['defense', 'Ministry of National Defense', 101692, false, 'p. 24'],
            ['interior', 'Ministry of Interior and Municipalities', 57235, false, 'p. 24'],
            ['education', 'Ministry of Education and Higher Education', 54859, false, 'p. 24'],
            ['health', 'Ministry of Public Health', 47266, false, 'p. 24'],
            ['council-ministers', 'Presidency of the Council of Ministers', 35466, false, 'p. 24'],
            ['public-works', 'Ministry of Public Works and Transport', 23710, false, 'p. 24'],
            ['telecom', 'Ministry of Telecommunications', 20384, false, 'p. 24'],
            ['social-affairs', 'Ministry of Social Affairs', 16263, false, 'p. 24'],
            ['labor', 'Ministry of Labor', 13425, false, 'p. 24'],
            ['energy-water', 'Ministry of Energy and Water', 10642, false, 'p. 24'],
            ['finance', 'Ministry of Finance', 7875, false, 'p. 24'],
            ['foreign-affairs', 'Ministry of Foreign Affairs and Emigrants', 6715, false, 'p. 24'],
            ['justice', 'Ministry of Justice', 4444, false, 'p. 24'],
            ['agriculture', 'Ministry of Agriculture', 2322, false, 'p. 24'],
            ['total-revenue', 'Total estimated revenue', 538415, true, 'p. 26', 'revenue'],
            ['tax-revenue', 'Tax revenue', 439613, false, 'p. 26', 'revenue'],
            ['non-tax-revenue', 'Non-tax revenue', 98802, false, 'p. 26', 'revenue'],
        ];
        return array_map(fn (array $row): array => $this->financialPayload('2026:'.$row[0], $row[5] ?? 'allocation', '2026', $row[1], $row[2], $row[3], $row[4], $url, '2026 Citizen Budget'), $rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function finance2025Rows(string $text, string $url): array
    {
        if (! str_contains(str_replace(["\xc2\xa0", ' '], '', $text), '424,109') && ! str_contains($text, '424 109')) {
            throw new RuntimeException('The 2025 expenditure total anchor was not found; source format may have changed.');
        }
        $rows = [
            ['total-expenditure', 'Total expenditures (budget and treasury)', 424109, 'p. 4; Table 6, p. 13', 'reported_expenditure'],
            ['total-revenue', 'Total revenues (budget and treasury)', 553414, 'Table 4, p. 11', 'revenue'],
            ['budget-revenue', 'Budget revenues', 493725, 'Table 4, p. 11', 'revenue'],
            ['tax-revenue', 'Tax revenues', 415853, 'Table 4, p. 11', 'revenue'],
            ['non-tax-revenue', 'Non-tax revenues', 77872, 'Table 4, p. 11', 'revenue'],
            ['treasury-receipts', 'Treasury receipts', 59689, 'Table 4, p. 11', 'revenue'],
        ];
        return array_map(fn (array $row): array => $this->financialPayload('2025:'.$row[0], $row[4], '2025', $row[1], $row[2], true, $row[3], $url, 'Public Finance Monitor Annual 2025', ['reporting_period_start' => '2025-01-01', 'reporting_period_end' => '2025-12-31', 'accounting_scope' => 'budget_and_treasury']), $rows);
    }

    /** @return array<string, mixed> */
    private function financialPayload(string $key, string $type, string $period, string $category, int $amount, bool $total, string $page, string $url, string $version, array $extra = []): array
    {
        return [...$extra, 'external_key' => $key, 'fiscal_period' => $period, 'measure_type' => $type, 'category' => $category, 'amount' => number_format($amount, 4, '.', ''), 'amount_text' => number_format($amount).' billion LBP', 'currency' => 'LBP', 'original_unit' => 'LBP billion', 'scale' => 1000000000, 'source_version' => $version, 'page_reference' => $page, 'source_url' => $url, 'is_total' => $total, 'evidence' => ['source_document' => $url, 'page' => $page, 'extraction' => 'versioned_table_parser']];
    }

    private function document(PublicMoneySource $source, PublicMoneyImportRun $run, array $download, string $title, string $type): PublicMoneyDocument
    {
        $hash = hash('sha256', $download['body']);
        return PublicMoneyDocument::firstOrCreate(
            ['source_id' => $source->id, 'content_hash' => $hash],
            ['import_run_id' => $run->id, 'title' => $title, 'document_type' => $type, 'source_url' => $source->discovery_url, 'resolved_url' => $download['url'], 'mime_type' => $download['mime'], 'retrieved_at' => now(), 'parser_version' => 'public-money-v1', 'archive_body' => $type === 'pdf' ? base64_encode($download['body']) : $download['body'], 'archive_encoding' => $type === 'pdf' ? 'base64' : 'utf-8', 'archive_size' => strlen($download['body']), 'metadata' => ['official_source' => true]],
        );
    }

    private function upsertProcurement(array $data, bool $publish): string
    {
        return $this->upsertReviewed(ProcurementRecord::class, $data, $publish);
    }

    private function upsertFinancial(array $data, bool $publish): string
    {
        return $this->upsertReviewed(FinancialObservation::class, $data, $publish);
    }

    private function upsertReviewed(string $modelClass, array $data, bool $publish): string
    {
        $fingerprint = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $record = $modelClass::query()->where('source_id', $data['source_id'])->where('external_key', $data['external_key'])->first();
        if ($record && hash_equals((string) $record->fingerprint, $fingerprint)) {
            return 'updated';
        }
        $isNew = ! $record;
        $record ??= new $modelClass();
        $record->fill([...$data, 'fingerprint' => $fingerprint, 'review_status' => $publish ? 'approved' : 'pending', 'publication_status' => $publish ? 'published' : 'draft', 'reviewed_at' => $publish ? now() : null, 'published_at' => $publish ? now() : null, 'revision' => $isNew ? 1 : ((int) $record->revision) + 1]);
        $record->save();
        if ($publish) {
            PublicMoneyReviewAction::create(['subject_type' => $modelClass, 'subject_id' => $record->id, 'action' => 'publish', 'reason' => 'Initial verified import from the linked official source.', 'before' => null, 'after' => ['review_status' => 'approved', 'publication_status' => 'published']]);
        }
        return $isNew ? 'created' : 'updated';
    }

    private function date(?string $value): ?string
    {
        if (! $value || in_array(trim($value), ['', '-', 'N/A'], true)) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function absolutePpaUrl(string $href): string
    {
        return str_starts_with($href, 'https://') ? $href : 'https://www.ppa.gov.lb/'.ltrim($href, '/');
    }
}
