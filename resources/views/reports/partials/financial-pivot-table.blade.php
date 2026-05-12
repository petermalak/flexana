<table>
    <thead>
    <tr>
        <th>Date</th>
        <th>Day</th>
        @foreach ($columns as $col)
            <th>{{ $col }}</th>
        @endforeach
        <th>Total</th>
        <th>Value</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td class="left">{{ $row['date'] }}</td>
            <td class="left">{{ $row['day'] }}</td>
            @foreach ($columns as $col)
                <td>{{ ($row[$col] ?? 0) ?: '' }}</td>
            @endforeach
            <td>{{ $row['_total'] ?? 0 }}</td>
            <td class="right">{{ number_format((float) ($row['_value'] ?? 0), 2) }}</td>
        </tr>
    @endforeach
    @php
        $grandTotal = 0;
        $grandValue = 0;
        $colTotals = [];
        foreach ($columns as $col) {
            $ct = collect($rows)->sum($col);
            $colTotals[$col] = $ct;
            $grandTotal += $ct;
        }
        $grandValue = collect($rows)->sum('_value');
    @endphp
    <tr class="totals">
        <td class="left" colspan="2">Total</td>
        @foreach ($columns as $col)
            <td>{{ $colTotals[$col] }}</td>
        @endforeach
        <td>{{ $grandTotal }}</td>
        <td class="right">{{ number_format($grandValue, 2) }}</td>
    </tr>
    <tr class="pct">
        <td class="left" colspan="2">Percentage</td>
        @foreach ($columns as $col)
            <td>{{ $grandTotal > 0 ? round(($colTotals[$col] / $grandTotal) * 100) . '%' : '0%' }}</td>
        @endforeach
        <td>100%</td>
        <td></td>
    </tr>
    </tbody>
</table>
