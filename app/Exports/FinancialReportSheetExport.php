<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialReportSheetExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private int $dataRowCount = 0;

    public function __construct(
        private readonly array $rows,
        private readonly array $columns,
        private readonly string $sheetTitle,
        private readonly string $headerColor = '0D9488',
    ) {}

    public function collection(): Collection
    {
        $data = collect($this->rows)->map(function (array $row): array {
            $line = [
                $row['date'],
                $row['day'],
            ];
            foreach ($this->columns as $col) {
                $line[] = ($row[$col] ?? 0) ?: '';
            }
            $line[] = $row['_total'] ?? 0;
            $line[] = $row['_value'] ?? 0;

            return $line;
        });

        $this->dataRowCount = $data->count();

        $grandTotal = 0;
        $grandValue = 0.0;
        $colTotals = [];
        foreach ($this->columns as $col) {
            $ct = collect($this->rows)->sum($col);
            $colTotals[] = $ct;
            $grandTotal += $ct;
        }
        $grandValue = collect($this->rows)->sum('_value');

        $totals = ['Total', ''];
        foreach ($colTotals as $ct) {
            $totals[] = $ct;
        }
        $totals[] = $grandTotal;
        $totals[] = round($grandValue, 2);

        $percentages = ['Percentage', ''];
        foreach ($colTotals as $ct) {
            $percentages[] = $grandTotal > 0
                ? round(($ct / $grandTotal) * 100).'%'
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

    public function styles(Worksheet $sheet): void
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $totalsRow = $lastRow - 1;
        $pctRow = $lastRow;

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => "FF{$this->headerColor}"],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ]);

        if ($this->dataRowCount > 0) {
            for ($r = 2; $r <= $this->dataRowCount + 1; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF9FAFB'],
                        ],
                    ]);
                }
            }
        }

        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE5E7EB'],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => 'FF6B7280'],
                ],
            ],
        ]);

        $sheet->getStyle("A{$pctRow}:{$lastCol}{$pctRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => 'FF6B7280']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF3F4F6'],
            ],
        ]);

        $colCount = count($this->columns);
        $totalColLetter = $this->colLetter($colCount + 3);
        $valueColLetter = $this->colLetter($colCount + 4);

        $sheet->getStyle("{$totalColLetter}1:{$totalColLetter}{$lastRow}")
            ->getFont()->setBold(true);
        $sheet->getStyle("{$valueColLetter}2:{$valueColLetter}{$lastRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00');

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->setAutoFilter("A1:{$lastCol}1");
    }

    private function colLetter(int $oneBasedIndex): string
    {
        $letter = '';
        while ($oneBasedIndex > 0) {
            $oneBasedIndex--;
            $letter = chr(65 + ($oneBasedIndex % 26)).$letter;
            $oneBasedIndex = intdiv($oneBasedIndex, 26);
        }

        return $letter;
    }
}
