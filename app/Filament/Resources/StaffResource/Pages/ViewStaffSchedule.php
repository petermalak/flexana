<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\AmeliaAppointments\AmeliaAppointmentResource;
use App\Filament\Resources\StaffResource;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewStaffSchedule extends Page
{
    use InteractsWithRecord;

    protected static string $resource = StaffResource::class;

    protected static ?string $title = 'Week schedule';

    /** Week start (Monday) for the displayed week */
    public ?string $weekStart = null;

    /** For add-appointment modal: selected day (Y-m-d) */
    public ?string $addForDate = null;

    /**
     * Ensure mounted action has a 'data' key so Livewire can bind form fields
     * (mountedActions.0.data.service_id, repeat_weekly, weekly_occurrence_count, etc.).
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        $result = parent::mountAction($name, $arguments, $context);

        if (str_starts_with($name, 'add_') && ! empty($this->mountedActions)) {
            $index = array_key_last($this->mountedActions);
            $current = $this->mountedActions[$index]['data'] ?? [];

            $defaults = [
                'service_id' => null,
                'start_time' => '16:00',
                'end_time' => '17:00',
                'repeat_weekly' => false,
                'weekly_occurrence_count' => 7,
            ];

            $this->mountedActions[$index]['data'] = array_merge($defaults, $current);
        }

        return $result;
    }

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        if ($this->weekStart === null) {
            $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_details')
                ->label('Edit details')
                ->icon('heroicon-o-pencil-square')
                ->url(StaffResource::getUrl('edit', ['record' => $this->getRecord()]))
                ->color('gray'),
            Action::make('open_appointments_table')
                ->label('Open appointments table')
                ->icon('heroicon-o-table-cells')
                ->url(fn () => AmeliaAppointmentResource::getUrl())
                ->color('primary'),
        ];
    }

    public function getTitle(): string | Htmlable
    {
        return $this->getRecord()->name ?? 'Staff schedule';
    }

    public function content(Schema $schema): Schema
    {
        $days = $this->getDays();
        $byDay = $this->getAppointmentsByDay();

        $daySections = collect($days)->map(function (array $day) use ($byDay) {
            $dateKey = $day['dateKey'];
            $appointments = $byDay[$dateKey] ?? [];
            $isToday = $dateKey === now()->format('Y-m-d');
            $description = $day['shortDate'] . ($isToday ? ' · Today' : '');

            return Section::make($day['label'])
                ->description($description)
                ->collapsible()
                ->collapsed(! $isToday)
                ->schema([
                    Html::make(fn () => $this->renderDayAppointmentsHtml($appointments)),
                    Action::make('add_' . $dateKey)
                        ->label('Add')
                        ->icon('heroicon-o-plus')
                        ->form(fn () => $this->getAddAppointmentFormSchema())
                        ->mountUsing(function () use ($dateKey) {
                            $this->addForDate = $dateKey;
                        })
                        ->modalHeading('Add appointment')
                        ->modalDescription(fn () => $this->addForDate ? Carbon::parse($this->addForDate)->format('l, F j, Y') : '')
                        ->action(function (array $data) {
                            $this->addAppointmentFromData($data);
                        }),
                ]);
        })->all();

        $weekRange = e($this->getWeekStartCarbon()->format('M j') . ' – ' . $this->getWeekStartCarbon()->copy()->addDays(6)->format('M j, Y'));

        return $schema->components([
            Section::make('Week schedule')
                ->description('Add and view appointments for this staff member.')
                ->schema([
                    Grid::make(3)->schema([
                        Action::make('previousWeek')
                            ->label('Previous')
                            ->icon('heroicon-o-chevron-left')
                            ->color('gray')
                            ->action('previousWeek'),
                        Html::make(fn () => '<div class="flex items-center justify-center text-base font-semibold text-gray-900 dark:text-white">' . $weekRange . '</div>'),
                        Action::make('nextWeek')
                            ->label('Next')
                            ->icon('heroicon-o-chevron-right')
                            ->iconPosition(\Filament\Support\Enums\IconPosition::After)
                            ->color('gray')
                            ->action('nextWeek'),
                    ]),
                    Group::make([
                        Grid::make(1)->schema($daySections)->extraAttributes(['class' => 'gap-3']),
                    ])->extraAttributes(['class' => 'space-y-3']),
                ])
                ->columns(1),
        ]);
    }

    protected function getAddAppointmentFormSchema(): array
    {
        $services = $this->getStaffServices();
        return [
            Forms\Components\Select::make('service_id')
                ->label('Service')
                ->options($services)
                ->searchable()
                ->placeholder('Select a service'),
            Forms\Components\TimePicker::make('start_time')
                ->label('Start')
                ->default('16:00')
                ->required(),
            Forms\Components\TimePicker::make('end_time')
                ->label('End')
                ->default('17:00')
                ->required(),
            Forms\Components\Toggle::make('repeat_weekly')
                ->label('Repeat weekly')
                ->helperText('Create the same time slot on the same weekday for multiple consecutive weeks (can span months).')
                ->default(false)
                ->live(),
            Forms\Components\TextInput::make('weekly_occurrence_count')
                ->label('Total weekly occurrences')
                ->numeric()
                ->minValue(2)
                ->maxValue(52)
                ->default(7)
                ->required(fn (Get $get) => (bool) $get('repeat_weekly'))
                ->visible(fn (Get $get) => (bool) $get('repeat_weekly'))
                ->helperText('Total appointments including this one (one per week). For example, 7 = this week plus 6 more.'),
        ];
    }

    /** @param  array<int, AppointmentModel>  $appointments */
    protected function renderDayAppointmentsHtml(array $appointments): string
    {
        if (empty($appointments)) {
            return '<div style="border:1px dashed #e5e7eb;border-radius:12px;padding:12px 14px;font-size:13px;color:#6b7280;text-align:center;">'
                . 'No appointments scheduled'
                . '</div>';
        }

        $rows = [];
        foreach ($appointments as $apt) {
            $name = e($apt->service->name ?? '—');
            $time = e($apt->booking_start->format('g:i a') . ' – ' . $apt->booking_end->format('g:i a'));
            $location = 'Default location';
            $status = strtolower($apt->status ?? 'approved');
            $statusLabel = e(ucfirst($status));
            $editUrl = AmeliaAppointmentResource::getUrl('edit', ['record' => $apt->getKey()]);

            $rows[] = '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-size:13px;line-height:1.35;background-color:#ffffff;">'
                . '<div style="font-family:ui-monospace,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace;font-size:12px;color:#6b7280;margin-bottom:2px;">'
                . $time
                . '</div>'
                . '<div style="font-weight:600;color:#111827;margin-bottom:2px;">'
                . $name
                . '</div>'
                . '<div style="font-size:12px;color:#6b7280;margin-bottom:4px;">'
                . e($location) . ' · ' . $statusLabel
                . '</div>'
                . '<div>'
                . '<a href="' . e($editUrl) . '" style="display:inline-flex;align-items:center;gap:4px;border-radius:9999px;border:1px solid #d1d5db;padding:2px 10px;font-size:11px;font-weight:500;color:#374151;text-decoration:none;background-color:#f9fafb;">'
                . 'Edit'
                . '</a>'
                . '</div>'
                . '</div>';
        }

        return implode('', $rows);
    }

    /** @param  array<string, mixed>  $data */
    public function addAppointmentFromData(array $data): void
    {
        if (! $this->addForDate) {
            Notification::make()->title('Invalid date.')->danger()->send();
            return;
        }

        $staff = $this->getRecord();
        $serviceId = $data['service_id'] ?? null;
        if ($serviceId === null || $serviceId === '') {
            Notification::make()
                ->title('Please select a service.')
                ->danger()
                ->send();
            return;
        }

        $serviceId = (int) $serviceId;
        $serviceExists = ServiceModel::query()->whereKey($serviceId)->exists();
        if (! $serviceExists) {
            Notification::make()
                ->title('The selected service is invalid.')
                ->danger()
                ->send();
            return;
        }

        $startVal = $data['start_time'] ?? null;
        $endVal = $data['end_time'] ?? null;
        $startStr = $startVal instanceof \Carbon\Carbon
            ? $startVal->format('H:i')
            : (string) $startVal;
        $endStr = $endVal instanceof \Carbon\Carbon
            ? $endVal->format('H:i')
            : (string) $endVal;
        $start = Carbon::parse($this->addForDate . ' ' . $startStr);
        $end = Carbon::parse($this->addForDate . ' ' . $endStr);
        if ($end->lte($start)) {
            Notification::make()->title('End time must be after start time.')->danger()->send();
            return;
        }

        $repeatWeekly = (bool) ($data['repeat_weekly'] ?? false);
        $totalWeeklyOccurrences = $repeatWeekly
            ? max(2, min(52, (int) ($data['weekly_occurrence_count'] ?? 2)))
            : 1;

        // Always handle the first appointment with conflict checking.
        $firstConflict = AppointmentModel::query()
            ->where('provider_id', $staff->getKey())
            ->where('booking_start', '<', $end)
            ->where('booking_end', '>', $start)
            ->first();

        if ($firstConflict) {
            Notification::make()
                ->title('This time overlaps with an existing appointment.')
                ->body('The instructor already has an appointment from ' . $firstConflict->booking_start->format('g:i A') . ' to ' . $firstConflict->booking_end->format('g:i A') . '. Please choose another time.')
                ->danger()
                ->send();
            return;
        }

        AppointmentModel::create([
            'provider_id' => $staff->getKey(),
            'service_id' => $serviceId,
            'booking_start' => $start,
            'booking_end' => $end,
            'status' => 'approved',
        ]);

        if (! $repeatWeekly || $totalWeeklyOccurrences <= 1) {
            Notification::make()->title('Appointment added')->success()->send();
            return;
        }

        $createdCount = 1; // we already created the first one
        $skippedConflicts = 0;

        for ($i = 1; $i < $totalWeeklyOccurrences; $i++) {
            $currentStart = $start->copy()->addWeeks($i);
            $currentEnd = $end->copy()->addWeeks($i);

            $conflict = AppointmentModel::query()
                ->where('provider_id', $staff->getKey())
                ->where('booking_start', '<', $currentEnd)
                ->where('booking_end', '>', $currentStart)
                ->first();

            if ($conflict) {
                $skippedConflicts++;
            } else {
                AppointmentModel::create([
                    'provider_id' => $staff->getKey(),
                    'service_id' => $serviceId,
                    'booking_start' => $currentStart->copy(),
                    'booking_end' => $currentEnd->copy(),
                    'status' => 'approved',
                ]);
                $createdCount++;
            }
        }

        $weeksRequested = $totalWeeklyOccurrences - 1;
        $message = $createdCount > 1
            ? "Added {$createdCount} weekly appointment(s) over " . ($weeksRequested + 1) . ' week(s).'
            : 'Appointment added';

        $notification = Notification::make()->title($message)->success();

        if ($skippedConflicts > 0) {
            $notification->body("Skipped {$skippedConflicts} conflicting time slot(s).");
        }

        $notification->send();
    }

    public function getWeekStartCarbon(): Carbon
    {
        return Carbon::parse($this->weekStart)->startOfDay();
    }

    public function getWeekEndCarbon(): Carbon
    {
        return $this->getWeekStartCarbon()->copy()->addDays(6)->endOfDay();
    }

    /** Get the 7 days (Mon–Sun) with date and label */
    public function getDays(): array
    {
        $start = $this->getWeekStartCarbon();
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $days[] = [
                'date' => $date,
                'dateKey' => $date->format('Y-m-d'),
                'label' => $date->format('l'), // Monday, Tuesday, ...
                'shortDate' => $date->format('M j'),
            ];
        }
        return $days;
    }

    /** Appointments for the current week for this staff, grouped by date (Y-m-d) */
    public function getAppointmentsByDay(): array
    {
        $staff = $this->getRecord();
        $weekStart = $this->getWeekStartCarbon();
        $weekEnd = $this->getWeekEndCarbon();

        $appointments = AppointmentModel::query()
            ->where('provider_id', $staff->getKey())
            ->whereBetween('booking_start', [$weekStart, $weekEnd])
            ->with('service')
            ->orderBy('booking_start')
            ->get();

        $byDay = [];
        foreach ($this->getDays() as $day) {
            $byDay[$day['dateKey']] = $appointments->filter(function ($apt) use ($day) {
                return $apt->booking_start->format('Y-m-d') === $day['dateKey'];
            })->values()->all();
        }
        return $byDay;
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->getWeekStartCarbon()->subWeek()->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->getWeekStartCarbon()->addWeek()->format('Y-m-d');
    }

    /** Services this staff can provide (for add-appointment dropdown) */
    public function getStaffServices(): array
    {
        return ServiceModel::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
