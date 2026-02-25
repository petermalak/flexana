<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\Page;
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
     * (mountedActions.0.data.service_id etc.). Filament does not add it by default.
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        $result = parent::mountAction($name, $arguments, $context);

        if (str_starts_with($name, 'add_') && count($this->mountedActions ?? []) > 0) {
            $index = array_key_last($this->mountedActions);
            if (! array_key_exists('data', $this->mountedActions[$index])) {
                $this->mountedActions[$index]['data'] = [
                    'service_id' => null,
                    'start_time' => '16:00',
                    'end_time' => '17:00',
                ];
            }
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
                        Grid::make(7)->schema($daySections)->extraAttributes(['class' => 'min-w-[42rem] gap-3']),
                    ])->extraAttributes(['class' => 'overflow-x-auto -mx-4 sm:-mx-6']),
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
                ->required()
                ->searchable()
                ->validationAttribute('Service')
                ->validationMessages([
                    'required' => 'Please select a service.',
                    'exists' => 'The selected service is invalid.',
                ])
                ->rules(['required', 'exists:services,id']),
            Forms\Components\TimePicker::make('start_time')
                ->label('Start')
                ->default('16:00')
                ->required(),
            Forms\Components\TimePicker::make('end_time')
                ->label('End')
                ->default('17:00')
                ->required(),
        ];
    }

    /** @param  array<int, AppointmentModel>  $appointments */
    protected function renderDayAppointmentsHtml(array $appointments): string
    {
        if (empty($appointments)) {
            return '<ul class="min-h-[4rem] space-y-1.5"><li class="py-3 text-center text-sm text-gray-400 dark:text-gray-500">No appointments</li></ul>';
        }
        $items = [];
        foreach ($appointments as $apt) {
            $name = e($apt->service->name ?? '—');
            $time = e($apt->booking_start->format('g:i') . '–' . $apt->booking_end->format('g:i A'));
            $items[] = '<li class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-2.5 py-2 text-sm dark:bg-gray-700/60">'
                . '<span class="truncate font-medium text-gray-800 dark:text-gray-200">' . $name . '</span>'
                . '<span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">' . $time . '</span></li>';
        }
        return '<ul class="min-h-[4rem] flex-1 space-y-1.5">' . implode('', $items) . '</ul>';
    }

    /** @param  array<string, mixed>  $data */
    public function addAppointmentFromData(array $data): void
    {
        if (! $this->addForDate) {
            Notification::make()->title('Invalid date.')->danger()->send();
            return;
        }

        $staff = $this->getRecord();
        $validated = \Illuminate\Support\Facades\Validator::make($data, [
            'service_id' => ['required', 'exists:services,id'],
            'start_time' => ['required'],
            'end_time' => ['required'],
        ], [
            'service_id.required' => 'Please select a service.',
            'service_id.exists' => 'The selected service is invalid.',
        ], [
            'service_id' => 'Service',
        ])->validate();

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

        $conflict = AppointmentModel::query()
            ->where('provider_id', $staff->getKey())
            ->where('booking_start', '<', $end)
            ->where('booking_end', '>', $start)
            ->first();
        if ($conflict) {
            Notification::make()
                ->title('This time overlaps with an existing appointment.')
                ->body('The instructor already has an appointment from ' . $conflict->booking_start->format('g:i A') . ' to ' . $conflict->booking_end->format('g:i A') . '. Please choose another time.')
                ->danger()
                ->send();
            return;
        }

        AppointmentModel::create([
            'provider_id' => $staff->getKey(),
            'service_id' => $validated['service_id'],
            'booking_start' => $start,
            'booking_end' => $end,
            'status' => 'approved',
        ]);
        Notification::make()->title('Appointment added')->success()->send();
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
        $staff = $this->getRecord();
        return $staff->services()->pluck('services.name', 'services.id')->all();
    }
}
