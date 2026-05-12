<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinancialReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
        private readonly string $periodLabel,
    ) {}

    /**
     * @return list<FinancialReportSheetExport>
     */
    public function sheets(): array
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
