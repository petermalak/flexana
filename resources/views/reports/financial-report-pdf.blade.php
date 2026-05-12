<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 16px 20px; }
        .header { margin-bottom: 14px; border-bottom: 2px solid #0d9488; padding-bottom: 8px; }
        .header h1 { font-size: 16px; color: #0d9488; letter-spacing: 0.5px; }
        .header .meta { font-size: 9px; color: #6b7280; margin-top: 2px; }

        h2 {
            font-size: 11px;
            margin: 16px 0 6px;
            padding: 4px 8px;
            color: #fff;
            border-radius: 3px;
            display: inline-block;
        }
        h2.yoga { background: #0d9488; }
        h2.reformer { background: #d97706; }
        h2.shop { background: #4f46e5; }
        h2.channel { background: #6366f1; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 3px 5px; text-align: center; font-size: 8.5px; }
        th {
            font-weight: 700;
            color: #fff;
            font-size: 9px;
        }
        th.yoga-header { background: #0d9488; }
        th.reformer-header { background: #d97706; }
        th.shop-header { background: #4f46e5; }
        th.channel-header { background: #6366f1; }

        td.left { text-align: left; }
        td.right { text-align: right; }
        tr:nth-child(even) td { background: #f9fafb; }
        tr.totals td { font-weight: 700; background: #e5e7eb !important; border-top: 2px solid #6b7280; }
        tr.pct td { font-style: italic; color: #6b7280; font-size: 8px; background: #f3f4f6 !important; }

        .footer { color: #9ca3af; font-size: 7.5px; margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial Report</h1>
        <div class="meta">Period: {{ $periodLabel }}</div>
    </div>

    @if (! empty($report['sections'] ?? []))
        {{-- Category-split layout (All categories export) --}}
        @foreach ($report['sections'] as $catLabel => $section)
            @if (count($section['rows'] ?? []) > 0)
                @php
                    $cssClass = strtolower($catLabel) === 'yoga' ? 'yoga' : 'reformer';
                @endphp
                <h2 class="{{ $cssClass }}">{{ $catLabel }} sales</h2>
                @include('reports.partials.financial-pivot-table', [
                    'columns' => $section['columns'],
                    'rows' => $section['rows'],
                    'headerClass' => $cssClass . '-header',
                ])
            @endif
        @endforeach

    @else
        {{-- Standard channel-split layout (filtered category export) --}}
        @php $columns = $report['columns'] ?? []; @endphp

        @foreach ($report['channels'] ?? [] as $channelLabel => $rows)
            @if (count($rows) > 0)
                <h2 class="channel">{{ $channelLabel }} sales</h2>
                @include('reports.partials.financial-pivot-table', [
                    'columns' => $columns,
                    'rows' => $rows,
                    'headerClass' => 'channel-header',
                ])
            @endif
        @endforeach

        @if (count($report['combined'] ?? []) > 0)
            <h2 class="channel">All channels combined</h2>
            @include('reports.partials.financial-pivot-table', [
                'columns' => $columns,
                'rows' => $report['combined'],
                'headerClass' => 'channel-header',
            ])
        @endif
    @endif

    <div class="footer">Zero-amount purchases are excluded. Values in local currency.</div>
</body>
</html>
