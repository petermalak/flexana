<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinancialReportExport implements WithMultipleSheets
{
    /**
     * @param  array  $report  Either a standard pivotReport or a categoryExportReport
     * @param  string  $periodLabel  Human-readable period, e.g. "2026-02-01 → 2026-02-28"
     * @param  bool  $categorySplit  True when the report uses the category-split format
     */
    public function __construct(
        private readonly array $report,
        private readonly string $periodLabel,
        private readonly bool $categorySplit = false,
    ) {}

    public function sheets(): array
    {
        if ($this->categorySplit) {
            return $this->categorySplitSheets();
        }

        return $this->standardSheets();
    }

    /**
     * Category-split: Yoga sheet + Reformer sheet.
     */
    private function categorySplitSheets(): array
    {
        $sheets = [];
        $colors = [
            'Yoga' => '0D9488',
            'Reformer' => 'D97706',
        ];

        foreach ($this->report['sections'] ?? [] as $label => $section) {
            if (empty($section['rows'])) {
                continue;
            }
            $sheets[] = new FinancialReportSheetExport(
                $section['rows'],
                $section['columns'],
                "{$label} sales {$this->periodLabel}",
                $colors[$label] ?? '0D9488',
            );
        }

        return $sheets;
    }

    /**
     * Standard: one sheet per channel + combined.
     */
    private function standardSheets(): array
    {
        $columns = $this->report['columns'] ?? [];
        $sheets = [];

        foreach ($this->report['channels'] ?? [] as $channelLabel => $rows) {
            if (empty($rows)) {
                continue;
            }
            $sheets[] = new FinancialReportSheetExport(
                $rows,
                $columns,
                "{$channelLabel} {$this->periodLabel}",
            );
        }

        $combined = $this->report['combined'] ?? [];
        if (! empty($combined)) {
            $sheets[] = new FinancialReportSheetExport(
                $combined,
                $columns,
                "All channels {$this->periodLabel}",
            );
        }

        return $sheets;
    }
}
