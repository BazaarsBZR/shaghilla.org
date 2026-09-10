<?php

namespace Tests\Feature;

use App\Models\FinancialObservation;
use App\Models\ProcurementRecord;
use App\Models\PublicMoneySource;
use App\Services\PublicMoney\PublicMoneyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicMoneyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_only_show_reviewed_published_records_and_keep_currencies_separate(): void
    {
        $source = $this->source();
        $this->procurement($source, 'visible-usd', 'Visible USD', 'USD', 'approved', 'published');
        $this->procurement($source, 'visible-lbp', 'Visible LBP', 'LBP', 'approved', 'published');
        $this->procurement($source, 'draft', 'Private Draft', 'USD', 'pending', 'draft');

        $response = $this->get('/public-money');
        $response->assertOk()->assertSee('Visible USD')->assertSee('Visible LBP')->assertDontSee('Private Draft');
        $response->assertSee('USD')->assertSee('LBP');
    }

    public function test_budget_allocations_and_reported_expenditure_are_labeled_separately(): void
    {
        $source = $this->source();
        foreach (['allocation', 'reported_expenditure'] as $type) {
            FinancialObservation::create([
                'source_id' => $source->id, 'external_key' => $type, 'fiscal_period' => '2025', 'measure_type' => $type,
                'category' => $type, 'amount' => 100, 'amount_text' => '100 billion LBP', 'currency' => 'LBP',
                'original_unit' => 'LBP billion', 'scale' => 1000000000, 'source_url' => $source->discovery_url,
                'is_total' => true, 'review_status' => 'approved', 'publication_status' => 'published', 'fingerprint' => hash('sha256', $type),
            ]);
        }
        $this->get('/public-money/budget')->assertOk()->assertSee('Budget allocations')->assertSee('Reported actual expenditure');
    }

    public function test_import_is_idempotent_and_missing_supplier_and_amount_remain_null(): void
    {
        $source = $this->source();
        $html = <<<'HTML'
<!doctype html><html><body><table><tbody><tr>
<td>Official Authority</td><td>Road maintenance award</td><td>Sep 10 2026</td><td>Sep 24 2026</td><td></td><td></td>
<td><a href="/en/tenders/details/12707/awards">Details</a></td>
</tr></tbody></table></body></html>
HTML;
        Http::fake([$source->discovery_url => Http::response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8'])]);

        app(PublicMoneyImporter::class)->import($source->key, 20, false);
        app(PublicMoneyImporter::class)->import($source->key, 20, false);

        $this->assertDatabaseCount('public_money_procurements', 1);
        $record = ProcurementRecord::firstOrFail();
        $this->assertNull($record->supplier);
        $this->assertNull($record->amount);
        $this->assertSame('pending', $record->review_status);
        $this->assertDatabaseCount('public_money_documents', 1);
        $this->assertDatabaseCount('public_money_import_runs', 2);
    }

    public function test_cron_endpoint_requires_bearer_secret(): void
    {
        putenv('CRON_SECRET=test-secret');
        $_ENV['CRON_SECRET'] = 'test-secret';
        $_SERVER['CRON_SECRET'] = 'test-secret';
        $this->post('/tasks/public-money-import')->assertNotFound();
    }

    private function source(): PublicMoneySource
    {
        return PublicMoneySource::create([
            'key' => 'ppa-awards', 'name_ar' => 'هيئة الشراء العام', 'name_en' => 'Public Procurement Authority',
            'adapter' => 'ppa_awards', 'base_url' => 'https://www.ppa.gov.lb', 'discovery_url' => 'https://www.ppa.gov.lb/en/awards',
            'is_enabled' => true, 'check_interval_minutes' => 1440,
        ]);
    }

    private function procurement(PublicMoneySource $source, string $key, string $title, string $currency, string $review, string $publication): ProcurementRecord
    {
        return ProcurementRecord::create([
            'source_id' => $source->id, 'external_key' => $key, 'stage' => 'award', 'title' => $title,
            'authority' => 'Official Authority', 'supplier' => 'Supplier', 'amount' => 10, 'amount_text' => '10 '.$currency,
            'currency' => $currency, 'event_on' => '2026-09-10', 'source_url' => $source->discovery_url,
            'review_status' => $review, 'publication_status' => $publication, 'fingerprint' => hash('sha256', $key),
        ]);
    }
}
