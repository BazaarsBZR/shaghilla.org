<?php

namespace App\Services\PublicMoney;

use App\Models\FinancialObservation;
use App\Models\PublicMoneySource;
use Illuminate\Support\Collection;

class FinancialPressureCalculator
{
    public const VERSION = '1.0.0';
    public const MAX_DATA_AGE_MONTHS = 24;

    /** @return array<string, mixed>|null */
    public function calculateAndStore(): ?array
    {
        $observations = FinancialObservation::query()
            ->publiclyVisible()
            ->whereIn('measure_type', [
                'public_debt_gross',
                'actual_total_revenue',
                'fiscal_balance',
                'inflation_rate',
            ])
            ->orderByDesc('reporting_period_end')
            ->get()
            ->groupBy('fiscal_period');

        foreach ($observations as $period => $rows) {
            $inputs = $this->compatibleInputs($rows);
            if (! $inputs) {
                continue;
            }

            $debtRevenueRatio = $this->normalisedAmount($inputs['debt']) / $this->normalisedAmount($inputs['revenue']);
            $fiscalBalanceRatio = $this->normalisedAmount($inputs['balance']) / $this->normalisedAmount($inputs['revenue']);
            $inflation = (float) $inputs['inflation']->amount;

            $components = [
                'debt_burden' => [
                    'label_ar' => 'عبء الدين مقابل الإيرادات',
                    'raw_value' => $debtRevenueRatio,
                    'unit' => 'ratio',
                    'score' => $this->clamp(($debtRevenueRatio - 1) / 7 * 100),
                    'weight' => 0.45,
                    'normalization' => '0 at ratio <= 1; 100 at ratio >= 8; linear between.',
                    'observation_ids' => [$inputs['debt']->id, $inputs['revenue']->id],
                ],
                'fiscal_balance' => [
                    'label_ar' => 'الرصيد المالي مقابل الإيرادات',
                    'raw_value' => $fiscalBalanceRatio,
                    'unit' => 'ratio',
                    'score' => $this->clamp((0.05 - $fiscalBalanceRatio) / 0.25 * 100),
                    'weight' => 0.30,
                    'normalization' => '0 at surplus >= 5% of revenue; 100 at deficit >= 20%; linear between.',
                    'observation_ids' => [$inputs['balance']->id, $inputs['revenue']->id],
                ],
                'inflation_pressure' => [
                    'label_ar' => 'ضغط التضخم',
                    'raw_value' => $inflation,
                    'unit' => 'percent',
                    'score' => $this->clamp(($inflation - 3) / 47 * 100),
                    'weight' => 0.25,
                    'normalization' => '0 at annual average inflation <= 3%; 100 at >= 50%; linear between.',
                    'observation_ids' => [$inputs['inflation']->id],
                ],
            ];

            $score = (int) round(collect($components)->sum(
                fn (array $component): float => $component['score'] * $component['weight']
            ));
            $source = $this->methodologySource();
            $externalKey = 'financial-pressure:'.self::VERSION.':'.$period;
            $observation = FinancialObservation::firstOrNew([
                'source_id' => $source->id,
                'external_key' => $externalKey,
            ]);
            $evidence = [
                'formula_version' => self::VERSION,
                'required_common_period' => (string) $period,
                'maximum_data_age_months' => self::MAX_DATA_AGE_MONTHS,
                'components' => $components,
                'calculated_at' => now()->toIso8601String(),
            ];
            $fingerprintEvidence = $evidence;
            unset($fingerprintEvidence['calculated_at']);
            $fingerprint = hash('sha256', json_encode($fingerprintEvidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $changed = $observation->exists && $observation->fingerprint && $observation->fingerprint !== $fingerprint;

            $observation->forceFill([
                'fiscal_period' => (string) $period,
                'reporting_period_start' => $inputs['revenue']->reporting_period_start,
                'reporting_period_end' => $inputs['revenue']->reporting_period_end,
                'measure_type' => 'financial_pressure_score',
                'authority' => 'Shaghilla',
                'category' => 'مؤشر الضغط المالي',
                'amount' => $score,
                'amount_text' => (string) $score,
                'currency' => 'INDEX',
                'original_unit' => 'score 0-100',
                'scale' => 1,
                'accounting_scope' => 'derived_from_compatible_official_observations',
                'source_version' => self::VERSION,
                'page_reference' => 'المنهجية الإصدار '.self::VERSION,
                'table_reference' => 'Financial Pressure Model',
                'row_reference' => (string) $period,
                'source_url' => 'https://shaghilla.org/financial-status#methodology',
                'is_total' => true,
                'overlap_group' => 'financial-pressure:'.$period,
                'evidence' => $evidence,
                'review_status' => 'approved',
                'publication_status' => 'published',
                'reviewed_at' => $observation->reviewed_at ?: now(),
                'published_at' => $observation->published_at ?: now(),
                'revision' => $changed ? ((int) $observation->revision + 1) : max(1, (int) $observation->revision),
                'fingerprint' => $fingerprint,
            ])->save();

            return ['score' => $score, 'period' => (string) $period, 'components' => $components];
        }

        return null;
    }

    /** @return array<string, FinancialObservation>|null */
    private function compatibleInputs(Collection $rows): ?array
    {
        $input = [
            'debt' => $rows->firstWhere('measure_type', 'public_debt_gross'),
            'revenue' => $rows->firstWhere('measure_type', 'actual_total_revenue'),
            'balance' => $rows->firstWhere('measure_type', 'fiscal_balance'),
            'inflation' => $rows->firstWhere('measure_type', 'inflation_rate'),
        ];

        if (in_array(null, $input, true)) {
            return null;
        }
        if ($input['debt']->currency !== 'LBP' || $input['revenue']->currency !== 'LBP' || $input['balance']->currency !== 'LBP') {
            return null;
        }
        if ($input['inflation']->currency !== 'PCT' || $this->normalisedAmount($input['revenue']) <= 0) {
            return null;
        }
        if (! $input['revenue']->reporting_period_end || $input['revenue']->reporting_period_end->lt(now()->subMonths(self::MAX_DATA_AGE_MONTHS))) {
            return null;
        }

        return $input;
    }

    private function methodologySource(): PublicMoneySource
    {
        return PublicMoneySource::updateOrCreate(
            ['key' => 'shaghilla-financial-pressure-v1'],
            [
                'name_ar' => 'شغيلة - منهجية مؤشر الضغط المالي',
                'name_en' => 'Shaghilla Financial Pressure Methodology',
                'adapter' => 'derived_financial_pressure',
                'base_url' => 'https://shaghilla.org/financial-status',
                'discovery_url' => 'https://shaghilla.org/financial-status#methodology',
                'is_enabled' => true,
                'check_interval_minutes' => 1440,
            ],
        );
    }

    private function normalisedAmount(FinancialObservation $observation): float
    {
        return (float) $observation->amount * (float) $observation->scale;
    }

    private function clamp(float $value): float
    {
        return min(100, max(0, $value));
    }
}
