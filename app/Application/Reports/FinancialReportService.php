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
     * Build a pivot-style report: one row per calendar day, one column per
     * purchase type (Drop-in + each package title), split by sales channel.
     *
     * Returns a structure ready for both the Filament table and Excel/PDF export:
     *
     *   [
     *     'columns'  => ['Drop-in', '5 Sessions', '10 Sessions', ...],
     *     'channels' => [
     *       'Application' => [ rows... ],
     *       'Website'     => [ rows... ],
     *     ],
     *     'combined' => [ rows... ],
     *   ]
     *
     * Each row: ['date' => '2026-02-01', 'day' => 'Saturday', 'Drop-in' => 3, '5 Sessions' => 1, ..., '_total' => 12, '_value' => 4200.00]
     */
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

        $period = CarbonPeriod::create($startsAt, $endsAt->endOfDay());
        $dates = collect($period)->map(fn (Carbon $d) => $d->format('Y-m-d'));

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
     * Raw per-payment detail: one row per (day, channel, purchase label) with qty and value.
     */
    protected function rawRows(CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $category = null): Collection
    {
        $driver = DB::connection()->getDriverName();
        $dayExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', sub.paid_at)",
            'pgsql' => "to_char((sub.paid_at AT TIME ZONE 'UTC')::date, 'YYYY-MM-DD')",
            default => 'DATE(sub.paid_at)',
        };

        // Subquery: compute channel + label as plain columns so the outer
        // GROUP BY only references simple column names (MySQL ONLY_FULL_GROUP_BY safe).
        $sub = DB::table('payments as p')
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

        if ($category === 'yoga') {
            $sub->whereRaw($this->serviceCategoryExpr()." = 'Yoga'");
        } elseif ($category === 'reformer') {
            $sub->whereRaw($this->serviceCategoryExpr()." = 'Reformer Pilates'");
        } elseif ($category === 'drop_in') {
            $sub->where('b.is_drop_in', true);
        }

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
     * SQL CASE that resolves each booking to 'Yoga', 'Reformer Pilates', or 'Other'.
     *
     * Priority: package.service_type (admin-set) > service name keyword match.
     * Drop-ins have no package, so we fall through to service-name inference.
     */
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

    /**
     * Pivot raw aggregated rows into one row per calendar day.
     *
     * @param  Collection<int, string>  $dates  Every date in the range
     * @param  list<string>  $columns  Package type column names
     * @param  Collection  $rawFiltered  Subset of rawRows for a channel
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
