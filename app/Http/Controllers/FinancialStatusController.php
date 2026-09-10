<?php

namespace App\Http\Controllers;

use App\Models\FinancialObservation;
use App\Models\PublicMoneySource;
use App\Services\PublicMoney\FinancialPressureCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FinancialStatusController extends Controller
{
    public function index(): View
    {
        $data = Cache::remember('financial-status.dashboard.v2', now()->addMinutes(15), function (): array {
        $observations = FinancialObservation::query()
            ->publiclyVisible()
            ->with(['source', 'document'])
            ->orderByDesc('reporting_period_end')
            ->orderByDesc('id')
            ->get();

        $latest = fn (string $measure) => $observations->firstWhere('measure_type', $measure);
        $scores = $observations->where('measure_type', 'financial_pressure_score')
            ->sortByDesc('fiscal_period')
            ->values();
        $score = $scores->first();
        if ($score?->reporting_period_end?->lt(now()->subMonths(FinancialPressureCalculator::MAX_DATA_AGE_MONTHS))) {
            $score = null;
        }

        $previousScore = $score
            ? $scores->first(fn (FinancialObservation $item) => $item->fiscal_period < $score->fiscal_period)
            : null;
        $scoreValue = $score ? (int) round((float) $score->amount) : null;
        $scoreChange = $score && $previousScore ? $scoreValue - (int) round((float) $previousScore->amount) : null;
        $latestRevenue = $latest('actual_total_revenue');
        $latestExpenditure = $latest('actual_total_expenditure');
        $debtRevenue = $this->debtRevenueComparison($observations);
        $revenueBreakdown = $latestRevenue
            ? $observations->where('fiscal_period', $latestRevenue->fiscal_period)
                ->whereIn('measure_type', ['actual_tax_revenue', 'actual_non_tax_revenue', 'actual_treasury_revenue'])
                ->keyBy('measure_type')
            : collect();
        $spending = $latestExpenditure
            ? $observations->where('measure_type', 'actual_expenditure_category')
                ->where('fiscal_period', $latestExpenditure->fiscal_period)
                ->values()
            : collect();
        $sources = PublicMoneySource::query()
            ->where(function ($query): void {
                $query->where('key', 'like', 'mof-finance-%')
                    ->orWhere('key', 'mof-public-debt-overview');
            })
            ->orderByDesc('last_success_at')
            ->get();

        return [
            'cards' => [
                'debt' => $latest('public_debt_gross'),
                'revenue' => $latestRevenue,
                'taxRevenue' => $latest('actual_tax_revenue'),
                'expenditure' => $latestExpenditure,
                'balance' => $latest('fiscal_balance'),
            ],
            'score' => $score,
            'scoreValue' => $scoreValue,
            'scoreLabel' => $this->scoreLabel($scoreValue),
            'scoreChange' => $scoreChange,
            'scoreExplanation' => $this->explanation($score, $previousScore, $scoreChange),
            'scoreHistory' => $scores->sortBy('fiscal_period')->values(),
            'debtRevenue' => $debtRevenue,
            'revenueBreakdown' => $revenueBreakdown,
            'spending' => $spending,
            'sources' => $sources,
            'lastTrustedUpdate' => $sources->max('last_success_at'),
            'hasSourceError' => $sources->contains(fn (PublicMoneySource $source) => filled($source->last_error)),
        ];
        });

        return view('pages.financial-status.index', $data);
    }

    /** @return array<string, mixed>|null */
    private function debtRevenueComparison(Collection $observations): ?array
    {
        foreach ($observations->where('measure_type', 'public_debt_gross') as $debt) {
            $revenue = $observations->first(fn (FinancialObservation $item) => $item->measure_type === 'actual_total_revenue'
                && $item->fiscal_period === $debt->fiscal_period
                && $item->currency === $debt->currency);
            if (! $revenue || $this->normalisedAmount($revenue) <= 0) {
                continue;
            }

            return [
                'period' => $debt->fiscal_period,
                'ratio' => $this->normalisedAmount($debt) / $this->normalisedAmount($revenue),
                'debt' => $debt,
                'revenue' => $revenue,
            ];
        }

        return null;
    }

    private function normalisedAmount(FinancialObservation $observation): float
    {
        return (float) $observation->amount * (float) $observation->scale;
    }

    private function scoreLabel(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score <= 25 => 'منخفض',
            $score <= 50 => 'متوسط',
            $score <= 75 => 'مرتفع',
            default => 'حرج',
        };
    }

    private function explanation(
        ?FinancialObservation $score,
        ?FinancialObservation $previous,
        ?int $change,
    ): string {
        if (! $score) {
            return 'لا تتوفر بيانات كافية لحساب المؤشر حاليًا';
        }
        if (! $previous || $change === null) {
            return 'لا تتوفر بعد فترة سابقة مكتملة ومتوافقة لشرح اتجاه المؤشر.';
        }
        if ($change === 0) {
            return 'لم يتغير الضغط المالي عن الفترة السابقة القابلة للمقارنة.';
        }

        $currentComponents = data_get($score->evidence, 'components', []);
        $previousComponents = data_get($previous->evidence, 'components', []);
        $causes = [];
        foreach ($currentComponents as $key => $component) {
            $before = data_get($previousComponents, $key.'.score');
            if ($before === null) {
                continue;
            }
            $difference = (float) $component['score'] - (float) $before;
            if (($change > 0 && $difference > .5) || ($change < 0 && $difference < -.5)) {
                $causes[] = $component['label_ar'];
            }
        }

        $direction = $change > 0 ? 'ارتفع' : 'انخفض';
        $causeText = $causes === [] ? '' : '، مدفوعًا بتغير '.implode(' و', $causes);

        return $direction.' الضغط المالي بمقدار '.abs($change).' نقاط عن الفترة السابقة'.$causeText.'.';
    }
}
