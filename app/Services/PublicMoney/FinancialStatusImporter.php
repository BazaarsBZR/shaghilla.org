<?php

namespace App\Services\PublicMoney;

use App\Models\FinancialObservation;
use App\Models\PublicMoneyDocument;
use App\Models\PublicMoneySource;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class FinancialStatusImporter
{
    public const REPORTS_INDEX = 'https://www.finance.gov.lb/en-us/Finance/Rep-Pub/DRI-MOF/PFR/';
    public const DEBT_INDEX = 'https://www.finance.gov.lb/en-us/Finance/PublicDebt/PDTS/';

    public function __construct(
        private readonly OfficialSourceClient $client,
        private readonly FinancialPressureCalculator $calculator,
    ) {
    }

    /** @return array<string, mixed> */
    public function import(): array
    {
        $lock = Cache::lock('public-money:financial-status-import', 90);
        if (! $lock->get()) {
            return ['ok' => true, 'locked' => true];
        }

        $results = [];
        $errors = [];

        try {
            try {
                $reportsPage = $this->client->get(self::REPORTS_INDEX);
                $annual = $this->latestLink(
                    $reportsPage['body'],
                    self::REPORTS_INDEX,
                    fn (string $text, string $href): bool => str_contains($text.' '.$href, 'annual')
                        && str_contains($text.' '.$href, 'pfm'),
                );
                $results['annual_report'] = $this->importAnnualReport($annual['url'], $annual['year']);
            } catch (Throwable $exception) {
                $errors['annual_report'] = $exception->getMessage();
                $this->markExistingSourceFailed('mof-finance-', $exception);
            }

            try {
                $debtPage = $this->client->get(self::DEBT_INDEX);
                $debt = $this->latestLink(
                    $debtPage['body'],
                    self::DEBT_INDEX,
                    fn (string $text, string $href): bool => str_contains($text.' '.$href, 'general')
                        && str_contains($text.' '.$href, 'debt')
                        && str_contains($text.' '.$href, 'overview'),
                );
                $results['public_debt'] = $this->importDebtOverview($debt['url'], $debt['year']);
            } catch (Throwable $exception) {
                $errors['public_debt'] = $exception->getMessage();
                $this->markExistingSourceFailed('mof-public-debt', $exception);
            }

            $score = $this->calculator->calculateAndStore();

            if ($results === []) {
                throw new RuntimeException('No official financial source could be refreshed.');
            }

            return [
                'ok' => $errors === [],
                'locked' => false,
                'sources' => $results,
                'score' => $score,
                'errors' => $errors,
            ];
        } finally {
            $lock->release();
        }
    }

    /** @return array{year: int, observations: int, document_id: int} */
    private function importAnnualReport(string $url, int $year): array
    {
        $source = $this->annualSource($year, $url);

        try {
            $response = $this->client->get($url);
            $hash = hash('sha256', $response['body']);
            $text = $this->pdfText($response['body']);
            $detectedYear = $this->annualYear($text) ?: $year;
            $previousYear = $detectedYear - 1;
            $document = $this->document(
                $source,
                'Public Finance Annual Review '.$detectedYear,
                'annual_public_finance_review',
                $url,
                $response['mime'],
                $response['body'],
                $hash,
            );

            $pairs = [
                ['actual_total_revenue', 'Total Budget and Treasury Receipts', 'Table 2, PDF page 2', $this->pair($text, 'Total Budget and Treasury Receipts')],
                ['actual_total_expenditure', 'Total Budget and Treasury Payments', 'Table 2, PDF page 2', $this->pair($text, 'Total Budget and Treasury Payments(?:,?\s+of which)?')],
                ['fiscal_balance', 'Total (Deficit)/Surplus', 'Table 2, PDF page 2', $this->pair($text, 'Total \(Deficit\)/Surplus')],
                ['actual_tax_revenue', 'Tax Revenues', 'Table 4, PDF page 12', $this->pair($text, 'Tax Revenues:?')],
                ['actual_non_tax_revenue', 'Non-Tax Revenues', 'Table 4, PDF page 12', $this->pair($text, 'Non-Tax Revenues')],
                ['actual_treasury_revenue', 'Treasury Receipts', 'Table 4, PDF page 12', $this->pair($text, 'Treasury Receipts(?:\s+1/)?')],
                ['inflation_rate', 'Average CPI Inflation', 'Table 1, PDF page 1', $this->pair($text, 'Average CPI Inflation \(%\)')],
            ];

            $count = 0;
            foreach ($pairs as [$measure, $category, $reference, $values]) {
                if (! $values) {
                    continue;
                }

                $isPercent = $measure === 'inflation_rate';
                $count += $this->upsertYearPair(
                    source: $source,
                    document: $document,
                    measure: $measure,
                    category: $category,
                    reference: $reference,
                    previousYear: $previousYear,
                    currentYear: $detectedYear,
                    values: $values,
                    currency: $isPercent ? 'PCT' : 'LBP',
                    unit: $isPercent ? 'percent' : 'LBP billion',
                    scale: $isPercent ? 1 : 1000000000,
                    sourceUrl: $url,
                    contentHash: $hash,
                    total: true,
                );
            }

            foreach ($this->expenditureCategories() as $category => $pattern) {
                $values = $this->pair($text, $pattern);
                if (! $values) {
                    continue;
                }

                $count += $this->upsertYearPair(
                    source: $source,
                    document: $document,
                    measure: 'actual_expenditure_category',
                    category: $category,
                    reference: 'Table 7, PDF pages 14-15',
                    previousYear: $previousYear,
                    currentYear: $detectedYear,
                    values: $values,
                    currency: 'LBP',
                    unit: 'LBP billion',
                    scale: 1000000000,
                    sourceUrl: $url,
                    contentHash: $hash,
                    total: false,
                );
            }

            $this->markSourceSuccessful($source, [
                'latest_report_year' => $detectedYear,
                'latest_document_hash' => $hash,
                'latest_document_id' => $document->id,
                'observations_processed' => $count,
            ]);

            return ['year' => $detectedYear, 'observations' => $count, 'document_id' => $document->id];
        } catch (Throwable $exception) {
            $this->markSourceFailed($source, $exception);
            throw $exception;
        }
    }

    /** @return array{year: int, observations: int, document_id: int} */
    private function importDebtOverview(string $url, int $year): array
    {
        $source = $this->debtSource($url);

        try {
            $response = $this->client->get($url);
            $hash = hash('sha256', $response['body']);
            $text = $this->pdfText($response['body']);
            $document = $this->document(
                $source,
                'General Debt Overview through '.$year,
                'public_debt_overview',
                $url,
                $response['mime'],
                $response['body'],
                $hash,
            );
            $values = $this->debtSeries($text);
            if ($values === []) {
                throw new RuntimeException('The gross-total-debt row was not found in the official debt overview.');
            }

            $count = 0;
            $endYear = $this->debtEndYear($text) ?: $year;
            $startYear = $endYear - count($values) + 1;
            foreach ($values as $offset => $raw) {
                $period = (string) ($startYear + $offset);
                $count += $this->upsertObservation(
                    source: $source,
                    document: $document,
                    measure: 'public_debt_gross',
                    category: 'Gross Total Debt',
                    period: $period,
                    amount: $this->number($raw),
                    amountText: $raw,
                    currency: 'LBP',
                    unit: 'LBP billion',
                    scale: 1000000000,
                    periodStart: $period.'-01-01',
                    periodEnd: $period.'-12-31',
                    reference: 'General Debt Overview, PDF page 1',
                    sourceUrl: $url,
                    contentHash: $hash,
                    total: true,
                    accountingScope: 'gross_total_debt_at_period_end',
                );
            }

            $this->markSourceSuccessful($source, [
                'latest_debt_year' => $endYear,
                'latest_document_hash' => $hash,
                'latest_document_id' => $document->id,
                'observations_processed' => $count,
            ]);

            return ['year' => $endYear, 'observations' => $count, 'document_id' => $document->id];
        } catch (Throwable $exception) {
            $this->markSourceFailed($source, $exception);
            throw $exception;
        }
    }

    /** @return array{url: string, year: int} */
    private function latestLink(string $html, string $baseUrl, callable $accept): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException('The Ministry of Finance publication index could not be parsed.');
        }

        $links = [];
        foreach ((new DOMXPath($document))->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $href = html_entity_decode(trim($anchor->getAttribute('href')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $anchor->textContent)));
            $candidate = mb_strtolower(rawurldecode($href));
            if (! $accept($text, $candidate) || ! preg_match('/(20\d{2})/', $text.' '.$candidate, $yearMatch)) {
                continue;
            }

            $year = (int) $yearMatch[1];
            $links[$year] = $this->absoluteUrl($href, $baseUrl);
        }

        if ($links === []) {
            throw new RuntimeException('No matching official publication link was found.');
        }

        krsort($links);
        $year = (int) array_key_first($links);

        return ['url' => $links[$year], 'year' => $year];
    }

    private function annualSource(int $year, string $url): PublicMoneySource
    {
        $source = PublicMoneySource::firstOrNew(['key' => 'mof-finance-'.$year]);
        $source->forceFill([
            'name_ar' => 'وزارة المالية - تقرير المالية العامة '.$year,
            'name_en' => 'Ministry of Finance - Public Finance Annual Review '.$year,
            'adapter' => $source->exists ? $source->adapter : 'mof_financial_status',
            'base_url' => 'https://www.finance.gov.lb',
            'discovery_url' => $url,
            'is_enabled' => true,
            'check_interval_minutes' => 1440,
        ])->save();

        return $source;
    }

    private function debtSource(string $url): PublicMoneySource
    {
        $source = PublicMoneySource::firstOrNew(['key' => 'mof-public-debt-overview']);
        $source->forceFill([
            'name_ar' => 'وزارة المالية - النظرة العامة للدين العام',
            'name_en' => 'Ministry of Finance - General Debt Overview',
            'adapter' => 'mof_public_debt',
            'base_url' => 'https://www.finance.gov.lb',
            'discovery_url' => $url,
            'is_enabled' => true,
            'check_interval_minutes' => 1440,
        ])->save();

        return $source;
    }

    private function document(
        PublicMoneySource $source,
        string $title,
        string $type,
        string $url,
        string $mime,
        string $body,
        string $hash,
    ): PublicMoneyDocument {
        $document = PublicMoneyDocument::firstOrNew([
            'source_id' => $source->id,
            'content_hash' => $hash,
        ]);
        $document->forceFill([
            'title' => $title,
            'document_type' => $type,
            'source_url' => $url,
            'resolved_url' => $url,
            'mime_type' => $mime,
            'retrieved_at' => now(),
            'parser_version' => 'financial-status-1',
            'archive_size' => strlen($body),
            'extraction_status' => 'parsed',
            'metadata' => [
                'sha256' => $hash,
                'retrieved_at' => now()->toIso8601String(),
            ],
        ])->save();

        return $document;
    }

    /**
     * @param array{0: string, 1: string} $values
     */
    private function upsertYearPair(
        PublicMoneySource $source,
        PublicMoneyDocument $document,
        string $measure,
        string $category,
        string $reference,
        int $previousYear,
        int $currentYear,
        array $values,
        string $currency,
        string $unit,
        int $scale,
        string $sourceUrl,
        string $contentHash,
        bool $total,
    ): int {
        $count = 0;
        foreach ([[$previousYear, $values[0]], [$currentYear, $values[1]]] as [$year, $raw]) {
            $count += $this->upsertObservation(
                source: $source,
                document: $document,
                measure: $measure,
                category: $category,
                period: (string) $year,
                amount: $this->number($raw),
                amountText: $raw,
                currency: $currency,
                unit: $unit,
                scale: $scale,
                periodStart: $year.'-01-01',
                periodEnd: $year.'-12-31',
                reference: $reference,
                sourceUrl: $sourceUrl,
                contentHash: $contentHash,
                total: $total,
                accountingScope: $measure === 'inflation_rate' ? 'annual_average_cpi' : 'budget_and_treasury_actuals',
            );
        }

        return $count;
    }

    private function upsertObservation(
        PublicMoneySource $source,
        PublicMoneyDocument $document,
        string $measure,
        string $category,
        string $period,
        float $amount,
        string $amountText,
        string $currency,
        string $unit,
        int $scale,
        string $periodStart,
        string $periodEnd,
        string $reference,
        string $sourceUrl,
        string $contentHash,
        bool $total,
        string $accountingScope,
    ): int {
        $observation = FinancialObservation::query()
            ->where('source_id', $source->id)
            ->where('measure_type', $measure)
            ->where('fiscal_period', $period)
            ->where('category', $category)
            ->where('currency', $currency)
            ->first();
        $observation ??= new FinancialObservation([
            'source_id' => $source->id,
            'external_key' => 'financial-status:'.hash('sha256', implode('|', [$measure, $category, $period, $currency])),
        ]);

        $fingerprint = hash('sha256', json_encode([
            $measure, $category, $period, $amountText, $currency, $unit, $reference, $contentHash,
        ], JSON_UNESCAPED_SLASHES));
        $changed = $observation->exists && $observation->fingerprint && $observation->fingerprint !== $fingerprint;

        $observation->forceFill([
            'document_id' => $document->id,
            'fiscal_period' => $period,
            'reporting_period_start' => $periodStart,
            'reporting_period_end' => $periodEnd,
            'measure_type' => $measure,
            'authority' => 'Ministry of Finance',
            'category' => $category,
            'amount' => $amount,
            'amount_text' => $amountText,
            'currency' => $currency,
            'original_unit' => $unit,
            'scale' => $scale,
            'accounting_scope' => $accountingScope,
            'source_version' => $contentHash,
            'page_reference' => $reference,
            'table_reference' => str_contains($reference, 'Table') ? strtok($reference, ',') : null,
            'row_reference' => $category,
            'source_url' => $sourceUrl,
            'is_total' => $total,
            'overlap_group' => implode(':', [$measure, $period, $currency, $accountingScope]),
            'evidence' => [
                'source_value' => $amountText,
                'original_unit' => $unit,
                'document_hash' => $contentHash,
                'retrieved_at' => now()->toIso8601String(),
                'extractor' => 'deterministic-table-row-v1',
            ],
            'review_status' => 'approved',
            'publication_status' => 'published',
            'reviewed_at' => $observation->reviewed_at ?: now(),
            'published_at' => $observation->published_at ?: now(),
            'revision' => $changed ? ((int) $observation->revision + 1) : max(1, (int) $observation->revision),
            'fingerprint' => $fingerprint,
        ])->save();

        return 1;
    }

    /** @return array{0: string, 1: string}|null */
    private function pair(string $text, string $labelPattern): ?array
    {
        if (! preg_match('~'.$labelPattern.'\s+(-?[\d,.]+)\s+(-?[\d,.]+)~iu', $text, $match)) {
            return null;
        }

        return [$match[1], $match[2]];
    }

    /** @return array<string, string> */
    private function expenditureCategories(): array
    {
        return [
            'Current Expenditures' => '1\.\s*Current Expenditures',
            'Capital Expenditures' => '2\.\s*Capital Expenditures',
            'Budget Advances' => '3\.\s*Budget Advances',
            'Treasury Expenditures' => '4\.\s*Treasury Expenditures',
        ];
    }

    /** @return array<int, string> */
    private function debtSeries(string $text): array
    {
        if (! preg_match('/Gross Total Debt.*?(?=I\.\s*Gross Domestic Debt)/isu', $text, $row)) {
            return [];
        }

        preg_match_all('/-?\d{1,3}(?:,\d{3})+(?:\.\d+)?|-?\d+(?:\.\d+)?/', $row[0], $matches);

        return array_values(array_filter($matches[0] ?? [], fn (string $value): bool => abs($this->number($value)) >= 1000));
    }

    private function annualYear(string $text): ?int
    {
        return preg_match('/Public Finance Annual Review\s+(20\d{2})/i', $text, $match) ? (int) $match[1] : null;
    }

    private function debtEndYear(string $text): ?int
    {
        if (preg_match('/(?:as\s+(?:at\s+)?(?:31\s*)?Dec|Updated\s+as\s+Dec)\D{0,8}(20\d{2})/iu', $text, $match)) {
            return (int) $match[1];
        }
        if (preg_match('/For\s+the\s+Period\s+end\s+20\d{2}\s*[-–]\s*end\s+(20\d{2})/iu', $text, $match)) {
            return (int) $match[1];
        }
        if (preg_match('/Dec[^\d]{0,5}(\d{2})(?!.*Dec[^\d]{0,5}\d{2})/isu', $text, $match)) {
            return 2000 + (int) $match[1];
        }

        return null;
    }

    private function pdfText(string $body): string
    {
        if (app()->environment('testing') && ! str_starts_with($body, '%PDF')) {
            return trim((string) preg_replace('/\s+/u', ' ', $body));
        }

        $text = (new Parser())->parseContent($body)->getText();
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            throw new RuntimeException('The official PDF did not contain extractable text.');
        }

        return $text;
    }

    private function number(string $raw): float
    {
        return (float) str_replace(',', '', $raw);
    }

    private function markSourceSuccessful(PublicMoneySource $source, array $metadata): void
    {
        $source->forceFill([
            'last_checked_at' => now(),
            'last_success_at' => now(),
            'last_error_at' => null,
            'last_error' => null,
            'metadata' => array_merge(is_array($source->metadata) ? $source->metadata : [], $metadata),
        ])->save();
    }

    private function markSourceFailed(PublicMoneySource $source, Throwable $exception): void
    {
        $source->forceFill([
            'last_checked_at' => now(),
            'last_error_at' => now(),
            'last_error' => mb_substr($exception->getMessage(), 0, 1000),
        ])->save();
    }

    private function markExistingSourceFailed(string $keyPrefix, Throwable $exception): void
    {
        $source = PublicMoneySource::query()->where('key', 'like', $keyPrefix.'%')->latest('id')->first();
        if ($source) {
            $this->markSourceFailed($source, $exception);
        }
    }

    private function absoluteUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'www.finance.gov.lb');
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $directory = rtrim(str_replace('\\', '/', dirname((string) ($parts['path'] ?? '/'))), '/');

        return $origin.($directory === '' ? '' : $directory).'/'.$href;
    }
}
