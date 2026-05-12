<?php

namespace App\Filament\Pages;

use App\Application\Reports\FinancialReportService;
use App\Exports\FinancialReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class FinancialReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'financial-report';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Financial report';

    protected static ?string $title = 'Financial report';

    protected static ?int $navigationSort = 15;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected ?array $cachedReport = null;

    public function mount(): void
    {
        $this->form->fill([
            'starts_at' => now()->startOfMonth()->format('Y-m-d'),
            'ends_at' => now()->format('Y-m-d'),
            'view_mode' => 'combined',
            'category' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reporting period')
                    ->description('Rows = each day with purchases. Columns = Drop-in + each package type. Application = mobile app; website = web checkout.')
                    ->columns(4)
                    ->schema([
                        DatePicker::make('starts_at')
                            ->label('From')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->autoRefresh()),
                        DatePicker::make('ends_at')
                            ->label('To')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('starts_at')
                            ->live()
                            ->afterStateUpdated(fn () => $this->autoRefresh()),
                        Select::make('category')
                            ->label('Category')
                            ->options([
                                '' => 'All',
                                'drop_in' => 'Drop-in (all)',
                                'yoga' => 'Yoga (packages + drop-ins)',
                                'reformer' => 'Reformer (packages + drop-ins)',
                            ])
                            ->default('')
                            ->live()
                            ->afterStateUpdated(fn () => $this->autoRefresh()),
                        Select::make('view_mode')
                            ->label('Channel')
                            ->options([
                                'combined' => 'All channels',
                                'Application' => 'Application only',
                                'Website' => 'Website only',
                            ])
                            ->default('combined')
                            ->live()
                            ->afterStateUpdated(fn () => $this->autoRefresh()),
                    ]),
            ])
            ->statePath('data');
    }

    public function autoRefresh(): void
    {
        $this->cachedReport = null;
        $this->flushCachedTableRecords();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ]),
                EmbeddedTable::make(),
            ]);
    }

    protected function applyPreset(callable $resolver): void
    {
        $this->form->fill($resolver());
        $this->autoRefresh();
    }

    // ─── Header actions (quick ranges + export) ─────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('thisMonth')
                    ->label('This month')
                    ->action(fn () => $this->applyPreset(fn () => [
                        ...$this->preservedFilters(),
                        'starts_at' => now()->startOfMonth()->format('Y-m-d'),
                        'ends_at' => now()->format('Y-m-d'),
                    ])),
                Action::make('lastMonth')
                    ->label('Last month')
                    ->action(fn () => $this->applyPreset(function (): array {
                        $start = now()->subMonthNoOverflow()->startOfMonth();

                        return [
                            ...$this->preservedFilters(),
                            'starts_at' => $start->format('Y-m-d'),
                            'ends_at' => $start->copy()->endOfMonth()->format('Y-m-d'),
                        ];
                    })),
                Action::make('last3Months')
                    ->label('Last 3 months')
                    ->action(fn () => $this->applyPreset(fn () => [
                        ...$this->preservedFilters(),
                        'starts_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->format('Y-m-d'),
                        'ends_at' => now()->format('Y-m-d'),
                    ])),
                Action::make('last12Months')
                    ->label('Last 12 months')
                    ->action(fn () => $this->applyPreset(fn () => [
                        ...$this->preservedFilters(),
                        'starts_at' => now()->subMonthsNoOverflow(11)->startOfMonth()->format('Y-m-d'),
                        'ends_at' => now()->format('Y-m-d'),
                    ])),
                Action::make('thisYear')
                    ->label('This calendar year')
                    ->action(fn () => $this->applyPreset(fn () => [
                        ...$this->preservedFilters(),
                        'starts_at' => now()->startOfYear()->format('Y-m-d'),
                        'ends_at' => now()->format('Y-m-d'),
                    ])),
                Action::make('fullYear')
                    ->label('Full calendar year')
                    ->action(fn () => $this->applyPreset(fn () => [
                        ...$this->preservedFilters(),
                        'starts_at' => now()->startOfYear()->format('Y-m-d'),
                        'ends_at' => now()->endOfYear()->format('Y-m-d'),
                    ])),
            ])
                ->label('Quick ranges')
                ->icon('heroicon-m-calendar-days')
                ->button(),
            Action::make('exportExcel')
                ->label('Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportExcel()),
            Action::make('exportPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn () => $this->exportPdf()),
        ];
    }

    // ─── Exports ────────────────────────────────────────────────────────

    protected function exportExcel(): mixed
    {
        $this->form->validate();
        [$report, $categorySplit] = $this->getExportReport();

        return Excel::download(
            new FinancialReportExport($report, $this->getPeriodLabel(), $categorySplit),
            $this->exportBaseFilename().'.xlsx',
        );
    }

    protected function exportPdf(): mixed
    {
        $this->form->validate();
        [$report] = $this->getExportReport();
        $filename = $this->exportBaseFilename().'.pdf';

        $pdf = Pdf::loadView('reports.financial-report-pdf', [
            'report' => $report,
            'periodLabel' => $this->getPeriodLabel(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * @return array{0: array, 1: bool}
     */
    protected function getExportReport(): array
    {
        $state = $this->data ?? [];
        $category = ($state['category'] ?? null) ?: null;

        if ($category === null) {
            $startsAt = CarbonImmutable::parse($state['starts_at'] ?? now()->startOfMonth()->format('Y-m-d'))->startOfDay();
            $endsAt = CarbonImmutable::parse($state['ends_at'] ?? now()->format('Y-m-d'))->endOfDay();

            return [app(FinancialReportService::class)->categoryExportReport($startsAt, $endsAt), true];
        }

        return [$this->getReport(), false];
    }

    protected function getPeriodLabel(): string
    {
        $state = $this->form->getState();

        return ($state['starts_at'] ?? '?').' → '.($state['ends_at'] ?? '?');
    }

    protected function exportBaseFilename(): string
    {
        $state = $this->form->getState();

        return 'financial-report-'.($state['starts_at'] ?? 'start').'-to-'.($state['ends_at'] ?? 'end');
    }

    // ─── Filament table (pivot layout) ──────────────────────────────────

    public function table(Table $table): Table
    {
        $report = $this->getReport();
        $columns = $report['columns'] ?? [];
        $rows = $this->getActiveRows();

        $tableColumns = [
            TextColumn::make('date')
                ->label('Date')
                ->sortable(),
            TextColumn::make('day')
                ->label('Day'),
        ];

        foreach ($columns as $col) {
            $tableColumns[] = TextColumn::make($col)
                ->label($col)
                ->numeric()
                ->default(0)
                ->alignCenter()
                ->description(fn (array $record): string => $record[$col.'_value'] > 0
                    ? number_format($record[$col.'_value'], 2)
                    : ''
                );
        }

        $tableColumns[] = TextColumn::make('_total')
            ->label('Total')
            ->numeric()
            ->weight('bold')
            ->alignCenter();

        $tableColumns[] = TextColumn::make('_value')
            ->label('Value')
            ->numeric(decimalPlaces: 2)
            ->weight('bold')
            ->alignEnd();

        return $table
            ->records(fn (): Collection => collect($rows))
            ->columns($tableColumns)
            ->paginated(false);
    }

    // ─── Data helpers ───────────────────────────────────────────────────

    protected function getReport(): array
    {
        if ($this->cachedReport !== null) {
            return $this->cachedReport;
        }

        $state = $this->data ?? [];
        $startsAt = $state['starts_at'] ?? now()->startOfMonth()->format('Y-m-d');
        $endsAt = $state['ends_at'] ?? now()->format('Y-m-d');

        $start = CarbonImmutable::parse($startsAt)->startOfDay();
        $end = CarbonImmutable::parse($endsAt)->endOfDay();

        if ($end->lt($start)) {
            return $this->cachedReport = ['columns' => [], 'channels' => [], 'combined' => []];
        }

        $category = $state['category'] ?? null;
        $category = $category ?: null;

        return $this->cachedReport = app(FinancialReportService::class)->pivotReport($start, $end, $category);
    }

    /**
     * @return array{view_mode: string, category: string}
     */
    protected function preservedFilters(): array
    {
        return [
            'view_mode' => $this->data['view_mode'] ?? 'combined',
            'category' => $this->data['category'] ?? '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getActiveRows(): array
    {
        $report = $this->getReport();
        $mode = $this->data['view_mode'] ?? 'combined';

        if ($mode === 'combined') {
            return $report['combined'] ?? [];
        }

        return $report['channels'][$mode] ?? [];
    }
}
