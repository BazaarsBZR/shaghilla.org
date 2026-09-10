<?php

namespace Tests\Feature;

use App\Models\ProcurementRecord;
use App\Services\PublicMoney\PpaTenderImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GovernmentTenderFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_official_tenders_are_imported_with_details_and_without_duplicates(): void
    {
        $this->fakePpa();
        $importer = app(PpaTenderImporter::class);

        $importer->import(pageLimit: 1, detailLimit: 5, timeBudgetSeconds: 30);
        $importer->import(pageLimit: 1, detailLimit: 5, timeBudgetSeconds: 30);

        $this->assertDatabaseCount('public_money_procurements', 1);
        $tender = ProcurementRecord::firstOrFail();
        $this->assertSame('12703', $tender->source_record_id);
        $this->assertSame('Ministry of Public Works', $tender->authority);
        $this->assertSame('Open tender', $tender->procurement_method);
        $this->assertSame('2030-10-10 09:00:00', $tender->submission_deadline_at->format('Y-m-d H:i:s'));
        $this->assertSame('USD', $tender->currency);
        $this->assertCount(1, $tender->tender_documents);
        $this->assertNotNull($tender->source_detail_hash);
        $this->assertSame('open', $tender->tenderStatusKey());
    }

    public function test_missing_source_rows_do_not_delete_historical_tenders(): void
    {
        $this->fakePpa();
        app(PpaTenderImporter::class)->import(pageLimit: 1, detailLimit: 5, timeBudgetSeconds: 30);

        Http::fake([
            PpaTenderImporter::LIST_URL.'*' => Http::response('<html><table><tbody></tbody></table></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        app(PpaTenderImporter::class)->import(pageLimit: 1, detailLimit: 0, timeBudgetSeconds: 30);

        $this->assertDatabaseCount('public_money_procurements', 1);
    }

    public function test_arabic_rtl_list_and_complete_detail_are_publicly_available(): void
    {
        $this->fakePpa();
        app(PpaTenderImporter::class)->import(pageLimit: 1, detailLimit: 5, timeBudgetSeconds: 30);

        $this->get('/government-tenders')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('المناقصات الحكومية')
            ->assertSee('ابحث في المناقصات')
            ->assertSee('Bridge maintenance');

        $this->get('/government-tenders/12703')
            ->assertOk()
            ->assertSee('كيف أشارك؟')
            ->assertSee('آخر موعد لتقديم العروض')
            ->assertSee('عرض المناقصة على الموقع الرسمي')
            ->assertSee('قيمة ضمان العرض')
            ->assertSee('https://www.ppa.gov.lb/en/tenders/details/12703', false);

        $this->assertStringContainsString(
            'المناقصات الحكومية',
            file_get_contents(resource_path('views/components/site/header.blade.php')),
        );
    }

    private function fakePpa(): void
    {
        $list = <<<'HTML'
        <html><body>
        <table>
          <thead><tr><th>ID</th><th>Procuring Entity</th><th>Purchase Code</th><th>Tender Title</th><th>Procurement Method</th><th>Announced Date</th><th>Opening Offers Date</th><th></th></tr></thead>
          <tbody><tr>
            <td>12703</td><td>Ministry of Public Works</td><td>PPA-2026-44</td><td>Bridge maintenance</td><td>Open tender</td><td>01/09/2026</td><td>10/10/2030 12:00 PM</td>
            <td><a href="/en/tenders/details/12703">Details</a></td>
          </tr></tbody>
        </table>
        </body></html>
        HTML;

        $detail = <<<'HTML'
        <html><body>
          <span class="badge status">Active</span>
          <table><tbody>
            <tr><th>Procuring Entity</th><td>Ministry of Public Works</td></tr>
            <tr><th>Tender Title</th><td>Bridge maintenance</td></tr>
            <tr><th>Purchase Code</th><td>PPA-2026-44</td></tr>
            <tr><th>Purchase Type</th><td>Works</td></tr>
            <tr><th>Sector</th><td>Infrastructure</td></tr>
            <tr><th>Procuring Method</th><td>Open tender</td></tr>
            <tr><th>Award Criteria</th><td>Lowest compliant offer</td></tr>
            <tr><th>Minimum Estimated Value</th><td>100,000</td></tr>
            <tr><th>Maximum Estimated Value</th><td>125,000</td></tr>
            <tr><th>Estimated Value Is Confidential</th><td>No</td></tr>
            <tr><th>Offer Guarantee Value</th><td>USD 5,000</td></tr>
            <tr><th>Currency</th><td>USD</td></tr>
            <tr><th>Announcement Date</th><td>01/09/2026</td></tr>
            <tr><th>Deadline for Submitting Offers</th><td>10/10/2030 12:00 PM</td></tr>
            <tr><th>Deadline for Clarification</th><td>01/10/2030 12:00 PM</td></tr>
            <tr><th>Administrative and Technical Opening Date</th><td>10/10/2030 12:30 PM</td></tr>
            <tr><th>Financial Opening Date</th><td>11/10/2030 12:00 PM</td></tr>
            <tr><th>Responsible Name</th><td>Procurement Office</td></tr>
            <tr><th>Responsible Phone</th><td>01 000 000</td></tr>
            <tr><th>Responsible Email</th><td>procurement@example.gov.lb</td></tr>
            <tr><th>Offer Submission Location</th><td>Central registry</td></tr>
            <tr><th>Eligibility Requirements</th><td>Valid registration stated in the official notice</td></tr>
            <tr><th>Required Documents</th><td>Documents listed in the bidding file</td></tr>
          </tbody></table>
          <a href="https://pe.ppa.gov.lb/storage/tenders/files/specifications.pdf">Tender specifications</a>
        </body></html>
        HTML;

        Http::fake([
            'https://www.ppa.gov.lb/en/tenders/details/*' => Http::response($detail, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
            PpaTenderImporter::LIST_URL.'*' => Http::response($list, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);
    }
}
