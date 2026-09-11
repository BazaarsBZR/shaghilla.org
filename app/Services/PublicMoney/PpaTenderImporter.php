<?php

namespace App\Services\PublicMoney;

use App\Models\ProcurementRecord;
use App\Models\PublicMoneyImportRun;
use App\Models\PublicMoneySource;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PpaTenderImporter
{
    public const SOURCE_KEY = 'ppa-tenders';
    public const LIST_URL = 'https://www.ppa.gov.lb/en/tenders';

    private const SOURCE_TIMEZONE = 'Asia/Beirut';

    private const DETAIL_PARSER_VERSION = 2;

    /** @var array<int, string>|null */
    private ?array $runColumns = null;

    public function __construct(private readonly OfficialSourceClient $client)
    {
    }

    /**
     * Import PPA tender announcements and hydrate official detail pages.
     *
     * Page one is always refreshed. Other pages advance through a persistent
     * cursor so short serverless jobs eventually cover the complete archive.
     *
     * @return array<string, int|string|bool|null>
     */
    public function import(
        int $pageLimit = 2,
        int $detailLimit = 14,
        bool $allPages = false,
        int $timeBudgetSeconds = 45,
    ): array {
        $lock = Cache::lock('public-money:ppa-tenders-import', max(60, $timeBudgetSeconds + 15));
        if (! $lock->get()) {
            return ['ok' => true, 'locked' => true, 'message' => 'A tender import is already running.'];
        }

        $startedAt = microtime(true);
        $source = $this->source();
        $run = $this->startRun($source);
        $seen = 0;
        $created = 0;
        $updated = 0;
        $detailUpdated = 0;
        $detailErrors = 0;

        try {
            $firstResponse = $this->client->get(self::LIST_URL);
            $maximumPage = $this->maximumPage($firstResponse['body']);
            $pages = $this->pagesForRun($source, $maximumPage, max(1, $pageLimit), $allPages);
            $pageBodies = [1 => $firstResponse['body']];
            $otherUrls = [];

            foreach ($pages as $page) {
                if ($page !== 1) {
                    $otherUrls[$this->pageUrl($page)] = $page;
                }
            }

            if ($otherUrls !== [] && ! $this->timeExceeded($startedAt, $timeBudgetSeconds)) {
                foreach ($this->client->getMany(array_keys($otherUrls), 6) as $url => $response) {
                    if (isset($response['body'])) {
                        $pageBodies[$otherUrls[$url]] = $response['body'];
                    }
                }
            }

            $priorityIds = [];
            foreach ($pageBodies as $page => $body) {
                foreach ($this->parseListPage($body, $this->pageUrl($page)) as $item) {
                    $seen++;
                    [$record, $wasCreated, $wasUpdated] = $this->upsertListRecord($source, $item);
                    $created += $wasCreated ? 1 : 0;
                    $updated += $wasUpdated ? 1 : 0;

                    if ($page === 1) {
                        $priorityIds[] = $record->id;
                    }
                }
            }

            if ($detailLimit > 0 && ! $this->timeExceeded($startedAt, $timeBudgetSeconds)) {
                $detailRecords = $this->detailCandidates($source, $priorityIds, $detailLimit);
                $urls = $detailRecords->pluck('source_url')->filter()->values()->all();
                $responses = $this->client->getMany($urls, 10);

                foreach ($detailRecords as $record) {
                    if ($this->timeExceeded($startedAt, $timeBudgetSeconds)) {
                        break;
                    }

                    $response = $responses[$record->source_url] ?? null;
                    if (! is_array($response) || ! isset($response['body'])) {
                        $detailErrors++;
                        continue;
                    }

                    try {
                        $detail = $this->parseDetailPage($response['body'], $record->source_url);
                        $this->applyDetail($record, $detail);
                        $detailUpdated++;
                    } catch (Throwable) {
                        $detailErrors++;
                    }
                }
            }

            $metadata = is_array($source->metadata) ? $source->metadata : [];
            $metadata['tenders'] = array_merge($metadata['tenders'] ?? [], [
                'maximum_page' => $maximumPage,
                'last_pages_checked' => array_values(array_keys($pageBodies)),
                'last_records_seen' => $seen,
                'last_details_updated' => $detailUpdated,
                'last_detail_errors' => $detailErrors,
                'last_completed_at' => now()->toIso8601String(),
            ]);

            $source->forceFill([
                'base_url' => self::LIST_URL,
                'discovery_url' => self::LIST_URL,
                'adapter' => 'government_tenders',
                'is_enabled' => true,
                'last_checked_at' => now(),
                'last_success_at' => now(),
                'last_error_at' => $detailErrors > 0 ? now() : null,
                'last_error' => $detailErrors > 0 ? $detailErrors.' tender detail page(s) could not be refreshed.' : null,
                'metadata' => $metadata,
            ])->save();

            $result = [
                'ok' => true,
                'locked' => false,
                'pages_checked' => count($pageBodies),
                'maximum_page' => $maximumPage,
                'records_seen' => $seen,
                'records_created' => $created,
                'records_updated' => $updated,
                'details_updated' => $detailUpdated,
                'detail_errors' => $detailErrors,
                'next_history_page' => data_get($metadata, 'tenders.next_history_page'),
            ];
            $this->finishRun($run, 'completed', $result);

            return $result;
        } catch (Throwable $exception) {
            $source->forceFill([
                'last_checked_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            $this->finishRun($run, 'failed', [
                'records_seen' => $seen,
                'records_created' => $created,
                'records_updated' => $updated,
                'error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function source(): PublicMoneySource
    {
        $source = PublicMoneySource::firstOrNew(['key' => self::SOURCE_KEY]);
        $source->forceFill([
            'name_ar' => 'إعلانات المناقصات لدى هيئة الشراء العام',
            'name_en' => 'Public Procurement Authority tender announcements',
            'base_url' => self::LIST_URL,
            'discovery_url' => self::LIST_URL,
            'adapter' => 'government_tenders',
            'is_enabled' => true,
            'check_interval_minutes' => 180,
        ]);
        $source->save();

        return $source;
    }

    /** @return array<int, int> */
    private function pagesForRun(PublicMoneySource $source, int $maximumPage, int $limit, bool $allPages): array
    {
        if ($allPages) {
            return range(1, max(1, $maximumPage));
        }

        $pages = [1];
        if ($maximumPage <= 1 || $limit <= 1) {
            return $pages;
        }

        $metadata = is_array($source->metadata) ? $source->metadata : [];
        $cursor = max(2, (int) data_get($metadata, 'tenders.next_history_page', 2));

        while (count($pages) < $limit) {
            if ($cursor > $maximumPage) {
                $cursor = 2;
            }
            $pages[] = $cursor;
            $cursor++;
            if (count(array_unique($pages)) >= $maximumPage) {
                break;
            }
        }

        $metadata['tenders'] = array_merge($metadata['tenders'] ?? [], [
            'next_history_page' => $cursor > $maximumPage ? 2 : $cursor,
        ]);
        $source->metadata = $metadata;
        $source->save();

        return array_values(array_unique($pages));
    }

    private function maximumPage(string $html): int
    {
        preg_match_all('/[?&]page=(\d+)/i', html_entity_decode($html), $matches);

        return max([1, ...array_map('intval', $matches[1] ?? [])]);
    }

    private function pageUrl(int $page): string
    {
        return $page <= 1 ? self::LIST_URL : self::LIST_URL.'?page='.$page;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function parseListPage(string $html, string $pageUrl): array
    {
        [$xpath] = $this->xpath($html);
        $rows = $xpath->query('//tr[.//a[contains(@href, "/tenders/details/")]]');
        $items = [];

        foreach ($rows ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $link = $xpath->query('.//a[contains(@href, "/tenders/details/")]', $row)?->item(0);
            $href = $link instanceof DOMElement ? trim($link->getAttribute('href')) : '';
            if (! preg_match('~/tenders/details/(\d+)~', $href, $idMatch)) {
                continue;
            }

            $cells = [];
            foreach ($xpath->query('./td', $row) ?: [] as $cell) {
                $cells[] = $this->text($cell);
            }

            $headers = [];
            $table = $row->parentNode?->parentNode;
            if ($table instanceof DOMNode) {
                foreach ($xpath->query('.//thead//th', $table) ?: [] as $header) {
                    $headers[] = $this->text($header);
                }
            }

            $items[] = [
                'source_record_id' => $idMatch[1],
                'authority' => $this->column($headers, $cells, ['procuring entity', 'الجهة الشارية']) ?? ($cells[1] ?? null),
                'reference_number' => $this->column($headers, $cells, ['purchase code', 'procurement reference', 'مرجع', 'رمز الشراء']) ?? ($cells[2] ?? null),
                'title' => $this->column($headers, $cells, ['tender title', 'title', 'عنوان']) ?? ($cells[3] ?? null),
                'procurement_method' => $this->column($headers, $cells, ['procurement method', 'طريقة الشراء']) ?? ($cells[4] ?? null),
                'announcement_raw' => $this->column($headers, $cells, ['announced in at', 'announced date', 'announcement date', 'تاريخ الإعلان']) ?? ($cells[5] ?? null),
                'opening_raw' => $this->column($headers, $cells, ['opening offers in', 'opening offers date', 'opening date', 'موعد فتح']) ?? ($cells[6] ?? null),
                'source_url' => $this->absoluteUrl($href, $pageUrl),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, string|null>  $item
     * @return array{ProcurementRecord, bool, bool}
     */
    private function upsertListRecord(PublicMoneySource $source, array $item): array
    {
        $externalKey = 'ppa:tender:'.$item['source_record_id'];
        $record = ProcurementRecord::firstOrNew([
            'source_id' => $source->id,
            'external_key' => $externalKey,
        ]);
        $wasCreated = ! $record->exists;
        $listHash = hash('sha256', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $wasUpdated = $wasCreated || $record->source_list_hash !== $listHash;
        $announcement = $this->date($item['announcement_raw']);
        $opening = $this->date($item['opening_raw']);
        $evidence = is_array($record->evidence) ? $record->evidence : [];

        $record->forceFill([
            'source_record_id' => $item['source_record_id'],
            'procurement_id' => $item['reference_number'],
            'reference_number' => $item['reference_number'],
            'stage' => 'tender',
            'original_stage' => 'Tender announcement',
            'status_normalized' => in_array($record->status_normalized, ['cancelled', 'awarded'], true)
                ? $record->status_normalized
                : 'active',
            'original_status' => $record->original_status ?: 'Published by PPA',
            'title' => $item['title'],
            'authority' => $item['authority'],
            'procurement_method' => $item['procurement_method'],
            'event_on' => $opening?->toDateString(),
            'publication_on' => $announcement?->toDateString(),
            'announcement_at' => $announcement,
            'submission_deadline_at' => $record->submission_deadline_at ?: $opening,
            'administrative_opening_at' => $record->administrative_opening_at ?: $opening,
            'source_url' => $item['source_url'],
            'source_status' => $record->source_status ?: 'published',
            'source_imported_at' => $record->source_imported_at ?: now(),
            'last_verified_at' => now(),
            'source_list_hash' => $listHash,
            'fingerprint' => $record->source_detail_hash ?: $listHash,
            'review_status' => 'approved',
            'publication_status' => 'published',
            'reviewed_at' => $record->reviewed_at ?: now(),
            'published_at' => $record->published_at ?: now(),
            'evidence' => array_merge($evidence, [
                'source' => 'Public Procurement Authority',
                'list_page' => [
                    'record_id' => $item['source_record_id'],
                    'announcement_date' => $item['announcement_raw'],
                    'opening_date' => $item['opening_raw'],
                    'operational_deadline_basis' => 'PPA listing: Opening offers in',
                    'verified_at' => now()->toIso8601String(),
                ],
            ]),
        ]);
        $record->save();

        return [$record, $wasCreated, $wasUpdated];
    }

    /**
     * @param  array<int, int>  $priorityIds
     * @return \Illuminate\Database\Eloquent\Collection<int, ProcurementRecord>
     */
    private function detailCandidates(PublicMoneySource $source, array $priorityIds, int $limit)
    {
        $priorityLimit = max(1, min($limit, (int) ceil($limit * 0.67)));
        $records = ProcurementRecord::query()
            ->where('source_id', $source->id)
            ->where('stage', 'tender')
            ->whereIn('id', $priorityIds)
            ->where(function ($query): void {
                $query->whereNull('detail_verified_at')
                    ->orWhere('detail_verified_at', '<', now()->subDay())
                    ->orWhereNull('evidence->detail_page->parser_version')
                    ->orWhere('evidence->detail_page->parser_version', '<', self::DETAIL_PARSER_VERSION);
            })
            ->orderByRaw('CASE WHEN detail_verified_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN submission_deadline_at IS NOT NULL AND submission_deadline_at >= ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('submission_deadline_at')
            ->orderBy('detail_verified_at')
            ->limit($priorityLimit)
            ->get();

        if ($records->count() >= $limit) {
            return $records;
        }

        $additional = ProcurementRecord::query()
            ->where('source_id', $source->id)
            ->where('stage', 'tender')
            ->whereNotIn('id', $records->pluck('id'))
            ->where(function ($query): void {
                $query->whereNull('detail_verified_at')
                    ->orWhere('detail_verified_at', '<', now()->subDay())
                    ->orWhereNull('evidence->detail_page->parser_version')
                    ->orWhere('evidence->detail_page->parser_version', '<', self::DETAIL_PARSER_VERSION);
            })
            ->orderByRaw('CASE WHEN detail_verified_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN submission_deadline_at IS NOT NULL AND submission_deadline_at >= ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('submission_deadline_at')
            ->orderBy('detail_verified_at')
            ->limit($limit - $records->count())
            ->get();

        return $records->concat($additional);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDetailPage(string $html, string $sourceUrl): array
    {
        [$xpath] = $this->xpath($html);
        $pairs = $this->fieldPairs($xpath);
        $documents = $this->documents($xpath, $sourceUrl);
        $stages = $this->stageLinks($xpath, $sourceUrl);

        $estimatedConfidential = $this->truthy($this->value($pairs, [
            'estimated value is confidential',
            'confidential estimated value',
            'القيمة التقديرية سرية',
        ]));
        $estimatedMinRaw = $this->value($pairs, ['minimum estimated value', 'min estimated value', 'الحد الأدنى للقيمة التقديرية']);
        $estimatedMaxRaw = $this->value($pairs, ['maximum estimated value', 'max estimated value', 'الحد الأقصى للقيمة التقديرية']);
        $estimatedConfidential = $estimatedConfidential
            || str_contains(mb_strtolower(($estimatedMinRaw ?? '').' '.($estimatedMaxRaw ?? '')), 'confidential')
            || str_contains(($estimatedMinRaw ?? '').' '.($estimatedMaxRaw ?? ''), 'سرية');
        $estimatedRaw = $this->value($pairs, ['estimated value', 'القيمة التقديرية']);
        if (! $estimatedMinRaw && ! $estimatedMaxRaw && $estimatedRaw) {
            $estimatedMinRaw = $estimatedRaw;
            $estimatedMaxRaw = $estimatedRaw;
        }

        $statusText = $this->statusText($xpath);
        $status = $this->normalisedStatus($statusText, $stages);
        $submissionDeadlineRaw = $this->value($pairs, [
            'deadline for submitting offers',
            'deadline for submission of offers',
            'submission deadline',
            'deadline of submitting offers',
            'آخر موعد لتقديم العروض',
        ]);
        $announcementRaw = $this->value($pairs, ['announcement date', 'announced date', 'date of publish plan', 'تاريخ الإعلان']);
        $administrativeOpeningRaw = $this->value($pairs, [
            'administrative and technical opening date',
            'administrative/technical opening date',
            'date of the administrative and technical bid opening session',
            'موعد فتح العروض الإدارية',
        ]);
        $financialOpeningRaw = $this->value($pairs, [
            'financial opening date',
            'date of the financial bid opening session',
            'موعد فتح العروض المالية',
        ]);
        $clarificationRaw = $this->value($pairs, [
            'deadline for clarification',
            'deadline for clarification of the award result',
            'clarification deadline',
            'deadline of clarification requests',
            'آخر موعد للاستفسارات',
        ]);
        $offerGuaranteeRaw = $this->value($pairs, ['offer guarantee value', 'bid guarantee', 'قيمة ضمان العرض']);

        return [
            'title' => $this->value($pairs, ['tender title', 'procurement title', 'عنوان المناقصة']),
            'authority' => $this->value($pairs, ['procuring entity', 'purchasing entity', 'الجهة الشارية']),
            'reference_number' => $this->value($pairs, ['purchase code', 'procurement reference', 'reference number', 'مرجع الشراء']),
            'procurement_type' => $this->value($pairs, ['purchase type', 'procurement type', 'نوع الشراء']),
            'sector' => $this->value($pairs, ['sector', 'القطاع']),
            'procurement_method' => $this->value($pairs, ['procuring method', 'procurement method', 'طريقة الشراء']),
            'award_criteria' => $this->value($pairs, ['award criteria', 'معايير التلزيم']),
            'estimated_value_min' => $this->amount($estimatedMinRaw),
            'estimated_value_max' => $this->amount($estimatedMaxRaw),
            'estimated_value_min_raw' => $estimatedMinRaw,
            'estimated_value_max_raw' => $estimatedMaxRaw,
            'estimated_value_confidential' => $estimatedConfidential,
            'offer_guarantee_value' => $this->amount($offerGuaranteeRaw),
            'offer_guarantee_text' => $offerGuaranteeRaw,
            'currency' => $this->value($pairs, ['currency', 'العملة']),
            'announcement_at' => $this->date($announcementRaw),
            'submission_deadline_at' => $this->date($submissionDeadlineRaw),
            'clarification_deadline_at' => $this->date($clarificationRaw),
            'administrative_opening_at' => $this->date($administrativeOpeningRaw),
            'financial_opening_at' => $this->date($financialOpeningRaw),
            'announcement_raw' => $announcementRaw,
            'submission_deadline_raw' => $submissionDeadlineRaw,
            'clarification_deadline_raw' => $clarificationRaw,
            'administrative_opening_raw' => $administrativeOpeningRaw,
            'financial_opening_raw' => $financialOpeningRaw,
            'responsible_name' => $this->value($pairs, ['responsible name', 'contact name', 'اسم المسؤول']),
            'responsible_phone' => $this->value($pairs, ['responsible phone', 'phone number', 'phone', 'رقم الهاتف']),
            'responsible_email' => $this->value($pairs, ['responsible email', 'email', 'البريد الإلكتروني']),
            'submission_location' => $this->value($pairs, [
                'offer submission location',
                'submission method/location',
                'tender documents receipt location',
                'place of submission offers',
                'مكان تقديم العروض',
            ]),
            'eligibility_requirements' => $this->value($pairs, [
                'eligibility requirements',
                'participation requirements',
                'شروط الأهلية',
                'شروط المشاركة',
            ]),
            'required_documents' => $this->value($pairs, ['required documents', 'المستندات المطلوبة']),
            'source_status' => $statusText ?: 'published',
            'status_normalized' => $status,
            'tender_documents' => $documents,
            'procurement_stages' => $stages,
            'source_values' => array_filter([
                'announcement_date' => $announcementRaw,
                'submission_deadline' => $submissionDeadlineRaw,
                'clarification_deadline' => $clarificationRaw,
                'administrative_opening' => $administrativeOpeningRaw,
                'financial_opening' => $financialOpeningRaw,
                'estimated_value_min' => $estimatedMinRaw,
                'estimated_value_max' => $estimatedMaxRaw,
                'offer_guarantee' => $offerGuaranteeRaw,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    /** @param array<string, mixed> $detail */
    private function applyDetail(ProcurementRecord $record, array $detail): void
    {
        foreach ($detail as $key => $value) {
            if (($value === null || $value === '' || $value === []) && $record->getAttribute($key) !== null) {
                $detail[$key] = $record->getAttribute($key);
            }
        }
        if ($record->estimated_value_confidential) {
            $detail['estimated_value_confidential'] = true;
        }

        $detailHash = hash('sha256', json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $evidence = is_array($record->evidence) ? $record->evidence : [];
        $amountText = $detail['estimated_value_confidential']
            ? null
            : implode(' - ', array_values(array_filter([
                $detail['estimated_value_min_raw'],
                $detail['estimated_value_max_raw'] !== $detail['estimated_value_min_raw'] ? $detail['estimated_value_max_raw'] : null,
            ])));

        $record->forceFill([
            'procurement_id' => $detail['reference_number'] ?: $record->procurement_id,
            'reference_number' => $detail['reference_number'] ?: $record->reference_number,
            'title' => $detail['title'] ?: $record->title,
            'authority' => $detail['authority'] ?: $record->authority,
            'procurement_type' => $detail['procurement_type'],
            'sector' => $detail['sector'],
            'procurement_method' => $detail['procurement_method'] ?: $record->procurement_method,
            'award_criteria' => $detail['award_criteria'],
            'estimated_value_min' => $detail['estimated_value_min'],
            'estimated_value_max' => $detail['estimated_value_max'],
            'estimated_value_confidential' => $detail['estimated_value_confidential'],
            'offer_guarantee_value' => $detail['offer_guarantee_value'],
            'offer_guarantee_text' => $detail['offer_guarantee_text'],
            'amount' => $detail['estimated_value_min'] === $detail['estimated_value_max'] ? $detail['estimated_value_min'] : null,
            'amount_text' => $amountText ?: $record->amount_text,
            'currency' => $detail['currency'] ?: $record->currency,
            'announcement_at' => $detail['announcement_at'] ?: $record->announcement_at,
            'submission_deadline_at' => $detail['submission_deadline_at']
                ?: $record->submission_deadline_at
                ?: $record->administrative_opening_at,
            'clarification_deadline_at' => $detail['clarification_deadline_at'],
            'administrative_opening_at' => $detail['administrative_opening_at'] ?: $record->administrative_opening_at,
            'financial_opening_at' => $detail['financial_opening_at'],
            'event_on' => ($detail['submission_deadline_at']
                ?: $record->submission_deadline_at
                ?: $record->administrative_opening_at)?->toDateString() ?: $record->event_on,
            'publication_on' => $detail['announcement_at']?->toDateString() ?: $record->publication_on,
            'responsible_name' => $detail['responsible_name'],
            'responsible_phone' => $detail['responsible_phone'],
            'responsible_email' => $detail['responsible_email'],
            'submission_location' => $detail['submission_location'],
            'eligibility_requirements' => $detail['eligibility_requirements'],
            'required_documents' => $detail['required_documents'],
            'source_status' => $detail['source_status'],
            'original_status' => $detail['source_status'],
            'status_normalized' => $detail['status_normalized'],
            'tender_documents' => $detail['tender_documents'],
            'procurement_stages' => $detail['procurement_stages'],
            'last_verified_at' => now(),
            'detail_verified_at' => now(),
            'source_detail_hash' => $detailHash,
            'fingerprint' => $detailHash,
            'evidence' => array_merge($evidence, [
                'detail_page' => [
                    'parser_version' => self::DETAIL_PARSER_VERSION,
                    'source_values' => $detail['source_values'],
                    'verified_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }

    /** @return array{DOMXPath, DOMDocument} */
    private function xpath(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException('The PPA page could not be parsed.');
        }

        return [new DOMXPath($document), $document];
    }

    /** @return array<int, array{key: string, value: string}> */
    private function fieldPairs(DOMXPath $xpath): array
    {
        $pairs = [];
        foreach ($xpath->query('//table//tr') ?: [] as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if (! $cells || $cells->length < 2) {
                continue;
            }

            $key = $this->text($cells->item(0));
            $values = [];
            for ($index = 1; $index < $cells->length; $index++) {
                $value = $this->text($cells->item($index));
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            if ($key !== '' && $values !== []) {
                $pairs[] = ['key' => $key, 'value' => implode(' | ', $values)];
            }
        }

        return $pairs;
    }

    /**
     * @param  array<int, array{key: string, value: string}>  $pairs
     * @param  array<int, string>  $needles
     */
    private function value(array $pairs, array $needles): ?string
    {
        foreach ($needles as $needle) {
            $normalisedNeedle = $this->normaliseLabel($needle);
            foreach ($pairs as $pair) {
                if (str_contains($this->normaliseLabel($pair['key']), $normalisedNeedle)) {
                    return $pair['value'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $cells
     * @param  array<int, string>  $needles
     */
    private function column(array $headers, array $cells, array $needles): ?string
    {
        foreach ($headers as $index => $header) {
            foreach ($needles as $needle) {
                if (str_contains($this->normaliseLabel($header), $this->normaliseLabel($needle))) {
                    return $cells[$index] ?? null;
                }
            }
        }

        return null;
    }

    /** @return array<int, array{name: string, url: string}> */
    private function documents(DOMXPath $xpath, string $sourceUrl): array
    {
        $documents = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $href = trim($anchor->getAttribute('href'));
            if (! preg_match('~(/storage/tenders/files/|\.(pdf|docx?|xlsx?)(?:\?|$))~i', $href)) {
                continue;
            }

            $url = $this->absoluteUrl($href, $sourceUrl);
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (! in_array($host, ['ppa.gov.lb', 'www.ppa.gov.lb', 'pe.ppa.gov.lb'], true)) {
                continue;
            }

            $name = $this->text($anchor);
            if ($name === '' || mb_strlen($name) < 3) {
                $name = basename((string) parse_url($url, PHP_URL_PATH));
            }
            $documents[$url] = ['name' => $name, 'url' => $url];
        }

        return array_values($documents);
    }

    /** @return array<int, array{stage: string, label: string, url: string}> */
    private function stageLinks(DOMXPath $xpath, string $sourceUrl): array
    {
        $definitions = [
            '/awards/' => ['stage' => 'award', 'label' => 'نتيجة تقييم أو تلزيم'],
            '/contracts/' => ['stage' => 'contract', 'label' => 'العقد الموقّع'],
            '/implementation/' => ['stage' => 'implementation', 'label' => 'تنفيذ العقد'],
        ];
        $links = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $href = trim($anchor->getAttribute('href'));
            foreach ($definitions as $fragment => $definition) {
                if (! str_contains($href, $fragment)) {
                    continue;
                }

                $url = $this->absoluteUrl($href, $sourceUrl);
                if (in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['ppa.gov.lb', 'www.ppa.gov.lb'], true)) {
                    $links[$definition['stage'].'|'.$url] = $definition + ['url' => $url];
                }
            }
        }

        return array_values($links);
    }

    private function statusText(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//*[contains(@class, "status") or contains(@class, "badge")]') ?: [] as $node) {
            $text = $this->text($node);
            if ($text !== '' && mb_strlen($text) <= 80) {
                return $text;
            }
        }

        return null;
    }

    /** @param array<int, array{stage: string, label: string, url: string}> $stages */
    private function normalisedStatus(?string $sourceStatus, array $stages): string
    {
        $status = mb_strtolower((string) $sourceStatus);
        if (str_contains($status, 'cancel') || str_contains($status, 'ملغ')) {
            return 'cancelled';
        }

        if (collect($stages)->contains(fn (array $stage) => $stage['stage'] === 'award')) {
            return 'awarded';
        }

        return 'active';
    }

    private function text(?DOMNode $node): string
    {
        if (! $node) {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function normaliseLabel(string $value): string
    {
        $value = mb_strtolower($this->textValue($value));

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
    }

    private function textValue(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function truthy(?string $value): bool
    {
        $value = mb_strtolower((string) $value);

        return in_array(trim($value), ['1', 'yes', 'true', 'y', 'نعم'], true)
            || str_contains($value, 'confidential')
            || str_contains($value, 'سري');
    }

    private function amount(?string $value): ?string
    {
        if (! $value || ! preg_match('/-?\d[\d,\s]*(?:\.\d+)?/', $value, $match)) {
            return null;
        }

        $number = str_replace([',', ' '], '', $match[0]);

        return is_numeric($number) ? $number : null;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, self::SOURCE_TIMEZONE)->utc();
        } catch (Throwable) {
            return null;
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
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'www.ppa.gov.lb');
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $directory = rtrim(str_replace('\\', '/', dirname((string) ($parts['path'] ?? '/'))), '/');

        return $origin.($directory === '' ? '' : $directory).'/'.$href;
    }

    private function timeExceeded(float $startedAt, int $budgetSeconds): bool
    {
        return microtime(true) - $startedAt >= max(5, $budgetSeconds - 3);
    }

    private function startRun(PublicMoneySource $source): ?PublicMoneyImportRun
    {
        try {
            $run = new PublicMoneyImportRun();
            $this->fillRun($run, [
                'source_id' => $source->id,
                'status' => 'running',
                'started_at' => now(),
            ]);
            $run->save();

            return $run;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $result */
    private function finishRun(?PublicMoneyImportRun $run, string $status, array $result): void
    {
        if (! $run) {
            return;
        }

        try {
            $this->fillRun($run, [
                'status' => $status,
                'finished_at' => now(),
                'discovered_count' => $result['records_seen'] ?? 0,
                'created_count' => $result['records_created'] ?? 0,
                'updated_count' => $result['records_updated'] ?? 0,
                'failed_count' => $result['detail_errors'] ?? ($status === 'failed' ? 1 : 0),
                'error_summary' => $result['error'] ?? null,
                'log' => $result,
            ]);
            $run->save();
        } catch (Throwable) {
            // Import-run telemetry must never prevent official records from updating.
        }
    }

    /** @param array<string, mixed> $values */
    private function fillRun(PublicMoneyImportRun $run, array $values): void
    {
        $this->runColumns ??= Schema::getColumnListing($run->getTable());
        $allowed = array_flip($this->runColumns);
        $run->forceFill(array_intersect_key($values, $allowed));
    }
}
