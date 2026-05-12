<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 12px; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 18px 0 4px; color: #0d9488; }
        .meta { font-size: 9px; color: #444; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 3px 5px; text-align: center; }
        th { background: #f3f4f6; font-weight: 600; }
        td.left { text-align: left; }
        td.right { text-align: right; }
        tr.totals td { font-weight: 700; background: #f9fafb; }
        tr.pct td { font-style: italic; color: #666; font-size: 8px; }
        .muted { color: #888; font-size: 8px; margin-top: 6px; }
    </style>
</head>
<body>
    <h1>Financial report</h1>
    <div class="meta">Period: {{ $periodLabel }}</div>

    @php $columns = $report['columns'] ?? []; @endphp

    @foreach ($report['channels'] ?? [] as $channelLabel => $rows)
        @if (count($rows) > 0)
            <h2>{{ $channelLabel }} sales</h2>
            @include('reports.partials.financial-pivot-table', ['columns' => $columns, 'rows' => $rows])
        @endif
    @endforeach

    @if (count($report['combined'] ?? []) > 0)
        <h2>All channels combined</h2>
        @include('reports.partials.financial-pivot-table', ['columns' => $columns, 'rows' => $report['combined']])
    @endif

    <p class="muted">Zero-amount purchases are excluded. Amounts shown in the "Value" column.</p>
</body>
</html>
