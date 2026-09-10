<?php

namespace Database\Seeders;

use App\Models\PublicMoneySource;
use Illuminate\Database\Seeder;

class PublicMoneySourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['key' => 'ppa-awards', 'name_ar' => 'هيئة الشراء العام - نتائج التلزيم', 'name_en' => 'Public Procurement Authority - Awards', 'adapter' => 'ppa_awards', 'base_url' => 'https://www.ppa.gov.lb', 'discovery_url' => 'https://www.ppa.gov.lb/en/awards'],
            ['key' => 'ppa-contracts', 'name_ar' => 'هيئة الشراء العام - العقود', 'name_en' => 'Public Procurement Authority - Contracts', 'adapter' => 'ppa_contracts', 'base_url' => 'https://www.ppa.gov.lb', 'discovery_url' => 'https://www.ppa.gov.lb/en/contracts'],
            ['key' => 'ppa-implementation', 'name_ar' => 'هيئة الشراء العام - التنفيذ', 'name_en' => 'Public Procurement Authority - Implementation', 'adapter' => 'ppa_implementation', 'base_url' => 'https://www.ppa.gov.lb', 'discovery_url' => 'https://www.ppa.gov.lb/en/implementation'],
            ['key' => 'mof-budget-2026', 'name_ar' => 'وزارة المالية - موازنة المواطن 2026', 'name_en' => 'Ministry of Finance - Citizen Budget 2026', 'adapter' => 'mof_budget_2026', 'base_url' => 'https://www.finance.gov.lb', 'discovery_url' => 'https://www.finance.gov.lb/en-us/Finance/BI/ABDP/Annual%20Budget%20Documents%20and%20Process/Citizen%20Budget%202026-Mar26-Ar.pdf'],
            ['key' => 'mof-finance-2025', 'name_ar' => 'وزارة المالية - تقرير المالية العامة 2025', 'name_en' => 'Ministry of Finance - Public Finance Monitor 2025', 'adapter' => 'mof_finance_2025', 'base_url' => 'https://www.finance.gov.lb', 'discovery_url' => 'https://www.finance.gov.lb/en-us/Finance/Rep-Pub/DRI-MOF/PFR/Public%20Finance%20Monitor/PFM%20Annual%202025.pdf'],
        ];
        foreach ($sources as $source) {
            PublicMoneySource::updateOrCreate(['key' => $source['key']], [...$source, 'is_enabled' => true, 'check_interval_minutes' => 1440]);
        }
    }
}
