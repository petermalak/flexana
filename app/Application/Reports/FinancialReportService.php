<?php

namespace App\Application\Reports;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * @param  string|null  $category  'yoga', 'reformer', 'drop_in', or null for all
     */
    public function pivotReport(CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $category = null): array
    {
        $raw = $this->rawRows($startsAt, $endsAt, $category);

        $columns = $raw->pluck('purchase_label')->unique()->sort()->values()->all();

        $channelMap = [
            'application' => 'Application',
            'website' => 'Website',
        ];

        $dates = $this->dateRange($startsAt, $endsAt);

        $channels = [];
        foreach ($channelMap as $bucket => $label) {
            $channels[$label] = $this->buildPivotRows(
                $dates,
                $columns,
                $raw->where('channel_bucket', $bucket),
            );
        }

        $combined = $this->buildPivotRows($dates, $columns, $raw);

        return [
            'columns' => $columns,
            'channels' => $channels,
            'combined' => $combined,
        ];
    }

    /**
     * Category-split report for the "All" export: separate Yoga / Reformer
     * pivot tables plus a Shop-wide summary (Qty + Value per day).
     *
     * @return array{
     *     sections: array<string, array{columns: list<string>, rows: list<array>}>,
     *     shop: list<array{date: string, day: string, qty: int, value: float}>,
     * }
     */
    public function categoryExportReport(CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $raw = $this->rawRowsWithCategory($startsAt, $endsAt);
        $dates = $this->dateRange($startsAt, $endsAt);

        $categoryLabels = [
            'Yoga' => 'Yoga',
            'Reformer Pilates' => 'Reformer',
        ];

        $sections = [];
        foreach ($categoryLabels as $catKey => $catLabel) {
            $subset = $raw->where('service_category', $catKey);
            $columns = $subset->pluck('purchase_label')->unique()->sort()->values()->all();
            $rows = $this->buildPivotRows($dates, $columns, $subset);
            $sections[$catLabel] = [
                'columns' => $columns,
                'rows' => $rows,
            ];
        }

        return [
            'sections' => $sections,
        ];
    }

    // ─── Raw queries ─────────────────────────────────────────────────

    protected function rawRows(CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $category = null): Collection
    {
        $sub = $this->baseSubquery($startsAt, $endsAt);

        if ($category === 'yoga') {
            $sub->whereRaw($this->serviceCategoryExpr()." = 'Yoga'");
        } elseif ($category === 'reformer') {
            $sub->whereRaw($this->serviceCategoryExpr()." = 'Reformer Pilates'");
        } elseif ($category === 'drop_in') {
            $sub->where('b.is_drop_in', true);
        }

        $dayExpr = $this->dayExpr();

        return DB::query()
            ->fromSub($sub, 'sub')
            ->selectRaw("{$dayExpr} as day")
            ->addSelect('sub.channel_bucket', 'sub.purchase_label')
            ->selectRaw('COUNT(*) as qty')
            ->selectRaw('SUM(sub.amount) as value')
            ->groupByRaw("{$dayExpr}, sub.channel_bucket, sub.purchase_label")
            ->orderBy('day')
            ->get();
    }

    /**
     * Like rawRows but also groups by service_category so the export can
     * split rows into Yoga / Reformer sections.
     */
    protected function rawRowsWithCategory(CarbonImmutable $startsAt, CarbonImmutable $endsAt): Collection
    {
        $sub = $this->baseSubquery($startsAt, $endsAt);
        $dayExpr = $this->dayExpr();

        return DB::query()
            ->fromSub($sub, 'sub')
            ->selectRaw("{$dayExpr} as day")
            ->addSelect('sub.channel_bucket', 'sub.purchase_label', 'sub.service_category')
            ->selectRaw('COUNT(*) as qty')
            ->selectRaw('SUM(sub.amount) as value')
            ->groupByRaw("{$dayExpr}, sub.channel_bucket, sub.purchase_label, sub.service_category")
            ->orderBy('day')
            ->get();
    }

    protected function baseSubquery(CarbonImmutable $startsAt, CarbonImmutable $endsAt)
    {
        return DB::table('payments as p')
            ->join('bookings as b', 'p.booking_id', '=', 'b.id')
            ->leftJoin('packages as pkg', 'b.package_id', '=', 'pkg.id')
            ->leftJoin('events as ev', 'b.event_id', '=', 'ev.id')
            ->leftJoin('services as svc', 'b.service_id', '=', 'svc.id')
            ->where('p.status', 'paid')
            ->whereNotNull('p.paid_at')
            ->where('p.amount', '>', 0)
            ->whereBetween('p.paid_at', [$startsAt->toDateTimeString(), $endsAt->toDateTimeString()])
            ->selectRaw('p.paid_at, p.amount')
            ->selectRaw("CASE WHEN b.channel IN ('mobile','ios','android') THEN 'application' WHEN b.channel = 'web' THEN 'website' ELSE COALESCE(b.channel,'other') END as channel_bucket")
            ->selectRaw("CASE WHEN b.is_drop_in THEN 'Drop-in' WHEN pkg.title IS NOT NULL THEN pkg.title WHEN ev.name IS NOT NULL THEN ev.name ELSE 'Other' END as purchase_label")
            ->selectRaw($this->serviceCategoryExpr().' as service_category');
    }

    protected function dayExpr(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', sub.paid_at)",
            'pgsql' => "to_char((sub.paid_at AT TIME ZONE 'UTC')::date, 'YYYY-MM-DD')",
            default => 'DATE(sub.paid_at)',
        };
    }

    protected function serviceCategoryExpr(): string
    {
        return <<<'SQL'
CASE
    WHEN pkg.service_type = 'Yoga'             THEN 'Yoga'
    WHEN pkg.service_type = 'Reformer Pilates' THEN 'Reformer Pilates'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%reformer%'     THEN 'Reformer Pilates'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%pilates%'      THEN 'Reformer Pilates'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%barre%'        THEN 'Reformer Pilates'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%yoga%'         THEN 'Yoga'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%vinyasa%'      THEN 'Yoga'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%hatha%'        THEN 'Yoga'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%flow%'         THEN 'Yoga'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%meditation%'   THEN 'Yoga'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%yin%'          THEN 'Yoga'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%breathwork%'   THEN 'Yoga'
    WHEN LOWER(COALESCE(svc.name,'')) LIKE '%sculpt%'       THEN 'Yoga'
    ELSE 'Other'
END
SQL;
    }

    // ─── Pivot builders ──────────────────────────────────────────────

    protected function dateRange(CarbonImmutable $startsAt, CarbonImmutable $endsAt): Collection
    {
        $period = CarbonPeriod::create($startsAt, $endsAt->endOfDay());

        return collect($period)->map(fn (Carbon $d) => $d->format('Y-m-d'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildPivotRows(Collection $dates, array $columns, Collection $rawFiltered): array
    {
        $grouped = $rawFiltered->groupBy('day');

        $rows = [];
        foreach ($dates as $date) {
            $dayData = $grouped->get($date, collect());
            $row = [
                'id' => $date,
                'date' => $date,
                'day' => Carbon::parse($date)->format('l'),
            ];

            $total = 0;
            $totalValue = 0.0;
            foreach ($columns as $col) {
                $match = $dayData->firstWhere('purchase_label', $col);
                $qty = $match ? (int) $match->qty : 0;
                $val = $match ? round((float) $match->value, 2) : 0.0;
                $row[$col] = $qty;
                $row[$col.'_value'] = $val;
                $total += $qty;
                $totalValue += $val;
            }

            $row['_total'] = $total;
            $row['_value'] = round($totalValue, 2);

            if ($total === 0) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
