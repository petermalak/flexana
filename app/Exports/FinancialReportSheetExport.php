<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class FinancialReportSheetExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $rows,
        private readonly array $columns,
        private readonly string $sheetTitle,
    ) {}

    public function collection(): Collection
    {
        $data = collect($this->rows)->map(function (array $row): array {
            $line = [
                $row['date'],
                $row['day'],
            ];
            foreach ($this->columns as $col) {
                $line[] = $row[$col] ?? 0;
            }
            $line[] = $row['_total'] ?? 0;
            $line[] = $row['_value'] ?? 0;

            return $line;
        });

        // Totals row
        $totals = ['Total', ''];
        $grandTotal = 0;
        $grandValue = 0.0;
        foreach ($this->columns as $col) {
            $colTotal = collect($this->rows)->sum($col);
            $totals[] = $colTotal;
            $grandTotal += $colTotal;
        }
        $grandValue = collect($this->rows)->sum('_value');
        $totals[] = $grandTotal;
        $totals[] = round($grandValue, 2);

        // Percentage row
        $percentages = ['Percentage', ''];
        foreach ($this->columns as $col) {
            $colTotal = collect($this->rows)->sum($col);
            $percentages[] = $grandTotal > 0
                ? round(($colTotal / $grandTotal) * 100).'%'
                : '0%';
        }
        $percentages[] = '100%';
        $percentages[] = '';

        $data->push($totals);
        $data->push($percentages);

        return $data;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Date', 'Day', ...$this->columns, 'Total', 'Value'];
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }
}
