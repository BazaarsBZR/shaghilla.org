<?php

namespace Tests\Feature;

use App\Models\FinancialObservation;
use App\Services\PublicMoney\FinancialStatusImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinancialStatusFeatureTest extends TestCase
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

    public function test_verified_financial_documents_are_imported_scored_and_idempotent(): void
    {
        $this->fakeOfficialFinanceSources();
        $importer = app(FinancialStatusImporter::class);

        $first = $importer->import();
        $recordCount = FinancialObservation::count();
        $second = $importer->import();

        $this->assertTrue($first['ok'], json_encode($first['errors'] ?? [], JSON_UNESCAPED_SLASHES));
        $this->assertTrue($second['ok'], json_encode($second['errors'] ?? [], JSON_UNESCAPED_SLASHES));
        $this->assertSame($recordCount, FinancialObservation::count());
        $this->assertDatabaseHas('public_money_financial_observations', [
            'measure_type' => 'actual_total_revenue',
            'fiscal_period' => '2025',
            'amount_text' => '553,414',
            'currency' => 'LBP',
            'original_unit' => 'LBP billion',
            'review_status' => 'approved',
            'publication_status' => 'published',
        ]);
        $this->assertDatabaseHas('public_money_financial_observations', [
            'measure_type' => 'public_debt_gross',
            'fiscal_period' => '2024',
            'amount_text' => '4,147,356',
        ]);

        $score = FinancialObservation::where('measure_type', 'financial_pressure_score')->firstOrFail();
        $this->assertSame('2024', $score->fiscal_period);
        $this->assertSame('1.0.0', data_get($score->evidence, 'formula_version'));
        $this->assertCount(3, data_get($score->evidence, 'components'));
        $this->assertGreaterThanOrEqual(51, (float) $score->amount);
        $this->assertLessThanOrEqual(75, (float) $score->amount);
        $this->assertSame(1, $score->fresh()->revision);
    }

    public function test_financial_dashboard_is_arabic_rtl_and_traces_official_periods(): void
    {
        $this->fakeOfficialFinanceSources();
        app(FinancialStatusImporter::class)->import();

        $this->get('/financial-status')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('الوضع المالي')
            ->assertSee('مؤشر الضغط المالي')
            ->assertSee('كيف نحسب المؤشر؟')
            ->assertSee('الفترة: 2025')
            ->assertSee('آخر تحديث موثوق')
            ->assertSee('فتح الوثيقة الرسمية')
            ->assertDontSee('قيمة تجريبية');

        $this->assertStringContainsString(
            'الوضع المالي',
            file_get_contents(resource_path('views/components/site/header.blade.php')),
        );
    }

    private function fakeOfficialFinanceSources(): void
    {
        $annualUrl = 'https://www.finance.gov.lb/reports/PFM%20Annual%202025.pdf';
        $debtUrl = 'https://www.finance.gov.lb/debt/General%20Debt%20Overview%20as%2031Dec2024.pdf';
        $annualText = <<<'TEXT'
        Public Finance Annual Review 2025
        Indicator 2024 2025
        Average CPI Inflation (%) 45.2 14.6
        Table 2 Summary of Fiscal Performance
        Total Budget and Treasury Receipts 348,233 553,414
        Total Budget and Treasury Payments, of which 326,851 424,109
        Total (Deficit)/Surplus 21,382 129,305
        Table 4 Total Revenues
        Tax Revenues 253,636 415,853
        Non-Tax Revenues 52,398 77,872
        Treasury Receipts 42,199 59,689
        Table 7 Expenditure by Economic Classification
        1. Current Expenditures 245,046 320,517
        2. Capital Expenditures 3,384 30,481
        3. Budget Advances 38,127 33,848
        4. Treasury Expenditures 36,508 33,837
        TEXT;
        $debtText = <<<'TEXT'
        Republic of Lebanon Ministry of Finance Public Debt Directorate
        General Debt Overview Updated as Dec 2024
        Dec-23 Dec-24
        Gross Total Debt (I+II)(1) 737,588 4,147,356
        I. Gross Domestic Debt 91,317 69,985
        For the Period end 2011 - end 2024
        (In LBP billions)
        TEXT;

        Http::fake([
            $annualUrl => Http::response($annualText, 200, ['Content-Type' => 'application/pdf']),
            $debtUrl => Http::response($debtText, 200, ['Content-Type' => 'application/pdf']),
            FinancialStatusImporter::REPORTS_INDEX => Http::response(
                '<a href="'.$annualUrl.'">PFM Annual 2025</a>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            FinancialStatusImporter::DEBT_INDEX => Http::response(
                '<a href="'.$debtUrl.'">General Debt Overview as 31Dec2024</a>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);
    }
}
