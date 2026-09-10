<?php

namespace App\Http\Controllers;

use App\Models\ProcurementRecord;
use App\Models\PublicMoneySource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GovernmentTenderController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureTenderSchema();

        $base = $this->baseQuery();
        $status = $request->string('status', 'open')->toString();
        $query = clone $base;

        $search = trim(mb_substr($request->string('q')->toString(), 0, 120));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('authority', 'like', '%'.$search.'%')
                    ->orWhere('reference_number', 'like', '%'.$search.'%')
                    ->orWhere('procurement_id', 'like', '%'.$search.'%');
            });
        }

        foreach ([
            'authority' => 'authority',
            'sector' => 'sector',
            'method' => 'procurement_method',
            'currency' => 'currency',
        ] as $parameter => $column) {
            $value = trim(mb_substr($request->string($parameter)->toString(), 0, 180));
            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        if ($request->filled('closing_date') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->string('closing_date')->toString())) {
            $query->whereDate('submission_deadline_at', $request->string('closing_date')->toString());
        }

        $this->applyStatus($query, $status);

        if ($request->string('sort')->toString() === 'newest') {
            $query->orderByDesc('announcement_at')->orderByDesc('source_imported_at');
        } else {
            $query->orderByRaw('CASE WHEN submission_deadline_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('submission_deadline_at')
                ->orderByDesc('announcement_at');
        }

        $tenders = $query->paginate(18)->withQueryString();
        $now = now();
        $metadata = Cache::remember('government-tenders.metadata.v2', now()->addMinutes(15), function () use ($base, $now): array {
            return [
                'source' => PublicMoneySource::query()->where('key', 'ppa-tenders')->first(),
                'counters' => [
                    'open' => $this->openQuery(clone $base)->count(),
                    'closing_week' => $this->openQuery(clone $base)
                        ->whereBetween('submission_deadline_at', [$now, $now->copy()->addDays(7)])
                        ->count(),
                    'recent' => (clone $base)->where(function (Builder $builder) use ($now): void {
                        $builder->where('announcement_at', '>=', $now->copy()->subDays(7))
                            ->orWhere('source_imported_at', '>=', $now->copy()->subDays(7));
                    })->count(),
                ],
                'filterOptions' => [
                    'authorities' => (clone $base)->whereNotNull('authority')->distinct()->orderBy('authority')->pluck('authority'),
                    'sectors' => (clone $base)->whereNotNull('sector')->distinct()->orderBy('sector')->pluck('sector'),
                    'methods' => (clone $base)->whereNotNull('procurement_method')->distinct()->orderBy('procurement_method')->pluck('procurement_method'),
                    'currencies' => (clone $base)->whereNotNull('currency')->distinct()->orderBy('currency')->pluck('currency'),
                ],
            ];
        });

        return view('pages.government-tenders.index', [
            'tenders' => $tenders,
            'source' => $metadata['source'],
            'status' => $status,
            'counters' => $metadata['counters'],
            'filterOptions' => $metadata['filterOptions'],
        ]);
    }

    public function show(string $sourceRecordId): View
    {
        $this->ensureTenderSchema();

        $tender = $this->baseQuery()
            ->where('source_record_id', $sourceRecordId)
            ->firstOrFail();

        $relatedStages = ProcurementRecord::query()
            ->publiclyVisible()
            ->whereIn('stage', ['award', 'contract', 'implementation'])
            ->where(function (Builder $query) use ($tender): void {
                $query->whereRaw('1 = 0');
                if ($tender->procurement_id) {
                    $query->orWhere('procurement_id', $tender->procurement_id);
                }
                if ($tender->reference_number) {
                    $query->orWhere('procurement_id', $tender->reference_number);
                }
                if ($tender->title && $tender->authority) {
                    $query->orWhere(function (Builder $match) use ($tender): void {
                        $match->where('title', $tender->title)->where('authority', $tender->authority);
                    });
                }
            })
            ->orderByRaw("CASE stage WHEN 'award' THEN 1 WHEN 'contract' THEN 2 ELSE 3 END")
            ->get();

        return view('pages.government-tenders.show', [
            'tender' => $tender,
            'relatedStages' => $relatedStages,
        ]);
    }

    private function baseQuery(): Builder
    {
        return ProcurementRecord::query()
            ->publiclyVisible()
            ->with('source')
            ->where('stage', 'tender')
            ->whereHas('source', fn (Builder $query) => $query->where('key', 'ppa-tenders'));
    }

    private function ensureTenderSchema(): void
    {
        $schemaReady = Cache::remember(
            'government-tenders.schema-ready.v1',
            now()->addHours(12),
            fn (): bool => Schema::hasColumn('public_money_procurements', 'submission_deadline_at'),
        );

        if (! $schemaReady) {
            Artisan::call('migrate', ['--force' => true]);
            Cache::put('government-tenders.schema-ready.v1', true, now()->addHours(12));
        }
    }

    private function applyStatus(Builder $query, string $status): void
    {
        match ($status) {
            'closing' => $this->openQuery($query)
                ->whereBetween('submission_deadline_at', [now(), now()->addDays(7)]),
            'expired' => $query->whereNotIn('status_normalized', ['cancelled', 'awarded'])
                ->whereNotNull('submission_deadline_at')
                ->where('submission_deadline_at', '<', now()),
            'cancelled' => $query->where('status_normalized', 'cancelled'),
            'awarded' => $query->where('status_normalized', 'awarded'),
            'all' => null,
            default => $this->openQuery($query),
        };
    }

    private function openQuery(Builder $query): Builder
    {
        return $query->whereNotIn('status_normalized', ['cancelled', 'awarded'])
            ->where(function (Builder $builder): void {
                $builder->whereNull('submission_deadline_at')
                    ->orWhere('submission_deadline_at', '>=', now());
            });
    }
}
