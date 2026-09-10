<?php

namespace App\Http\Controllers;

use App\Models\FinancialObservation;
use App\Models\ProcurementRecord;
use App\Models\PublicMoneyReport;
use App\Models\PublicMoneySource;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicMoneyController extends Controller
{
    public function index(): View
    {
        $procurements = ProcurementRecord::publiclyVisible()->with('source')->latest('event_on')->limit(8)->get();
        $stageCounts = ProcurementRecord::publiclyVisible()->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage');
        $currencyTotals = ProcurementRecord::publiclyVisible()->whereNotNull('amount')->whereNotNull('currency')->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency');
        $financial = FinancialObservation::publiclyVisible()->where('is_total', true)->latest('reporting_period_end')->get();
        $reports = PublicMoneyReport::query()->where('status', 'published')->latest('published_at')->limit(6)->get();

        return view('pages.public-money.index', compact('procurements', 'stageCounts', 'currencyTotals', 'financial', 'reports'));
    }

    public function procurements(Request $request): View
    {
        $query = ProcurementRecord::publiclyVisible()->with('source');
        $query->when($request->filled('q'), fn ($q) => $q->where(function ($nested) use ($request): void {
            $term = '%'.trim((string) $request->input('q')).'%';
            $nested->where('title', 'like', $term)->orWhere('authority', 'like', $term)->orWhere('supplier', 'like', $term);
        }));
        foreach (['stage', 'currency', 'authority', 'supplier'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }
        $records = $query->orderByDesc('event_on')->orderByDesc('id')->paginate(24)->withQueryString();
        $filters = [
            'stages' => ProcurementRecord::publiclyVisible()->whereNotNull('stage')->distinct()->orderBy('stage')->pluck('stage'),
            'currencies' => ProcurementRecord::publiclyVisible()->whereNotNull('currency')->distinct()->orderBy('currency')->pluck('currency'),
            'authorities' => ProcurementRecord::publiclyVisible()->distinct()->orderBy('authority')->pluck('authority'),
        ];
        return view('pages.public-money.procurements', compact('records', 'filters'));
    }

    public function procurement(ProcurementRecord $record): View
    {
        abort_unless($record->review_status === 'approved' && $record->publication_status === 'published', 404);
        $record->load(['source', 'document']);
        return view('pages.public-money.procurement', compact('record'));
    }

    public function budget(): View
    {
        $observations = FinancialObservation::publiclyVisible()->with('source')->orderByDesc('fiscal_period')->orderBy('measure_type')->orderByDesc('amount')->get();
        return view('pages.public-money.budget', compact('observations'));
    }

    public function sources(): View
    {
        $sources = PublicMoneySource::query()->where('is_enabled', true)->with(['runs' => fn ($query) => $query->latest('started_at')->limit(1)])->orderBy('name_en')->get();
        return view('pages.public-money.sources', compact('sources'));
    }

    public function report(PublicMoneyReport $report): View
    {
        abort_unless($report->status === 'published', 404);
        return view('pages.public-money.report', compact('report'));
    }
}
