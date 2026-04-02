<?php

namespace App\Filament\Resources\AmeliaAppointments\Schemas;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\CompanyOffDayModel;
use App\Infrastructure\Persistence\Eloquent\StaffOffDayModel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Operation;

class AmeliaAppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Appointment')
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('provider_id')
                            ->label('Instructor')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\DateTimePicker::make('booking_start')
                            ->label('Starts')
                            ->required()
                            ->rules([
                                fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get): void {
                                    $providerId = $get('provider_id');
                                    if (! $value || ! $providerId) {
                                        return;
                                    }
                                    $start = \Carbon\Carbon::parse($value);
                                    $date = $start->format('Y-m-d');

                                    $companyOff = CompanyOffDayModel::query()
                                        ->where('is_active', true)
                                        ->whereDate('date', $date)
                                        ->first();
                                    if ($companyOff) {
                                        $fail("The selected date falls on a company off day ({$companyOff->name}). Please choose another date.");
                                        return;
                                    }

                                    $staffOffDays = StaffOffDayModel::query()
                                        ->where('staff_id', $providerId)
                                        ->whereDate('date', $date)
                                        ->get();
                                    foreach ($staffOffDays as $off) {
                                        if ($off->is_all_day) {
                                            $fail('The instructor has an off day on the selected date. Please choose another date.');
                                            return;
                                        }
                                        $bookingEnd = $get('booking_end');
                                        if ($bookingEnd && $off->start_time && $off->end_time) {
                                            $end = \Carbon\Carbon::parse($bookingEnd);
                                            $dayStr = $start->format('Y-m-d');
                                            $offStart = \Carbon\Carbon::parse($dayStr . ' ' . \Carbon\Carbon::parse($off->start_time)->format('H:i:s'));
                                            $offEnd = \Carbon\Carbon::parse($dayStr . ' ' . \Carbon\Carbon::parse($off->end_time)->format('H:i:s'));
                                            if ($start->lt($offEnd) && $end->gt($offStart)) {
                                                $fail('The instructor has an off period that overlaps with this appointment time. Please choose another date or time.');
                                                return;
                                            }
                                        }
                                    }

                                    $bookingEnd = $get('booking_end');
                                    if ($bookingEnd) {
                                        $end = \Carbon\Carbon::parse($bookingEnd);
                                        $excludeId = $get('id');

                                        // Only consider conflicts on the same calendar day as the selected start time.
                                        $conflict = AppointmentModel::query()
                                            ->where('provider_id', $providerId)
                                            ->whereDate('booking_start', $start->toDateString())
                                            ->where(function ($q) use ($start, $end) {
                                                $q->where('booking_start', '<', $end)
                                                    ->where('booking_end', '>', $start);
                                            })
                                            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                                            ->first();
                                        if ($conflict) {
                                            $fail('The instructor already has another appointment at this time (' . $conflict->booking_start->format('M j, Y H:i') . ' – ' . $conflict->booking_end->format('H:i') . '). Please choose another time.');
                                            return;
                                        }
                                    }
                                },
                            ]),
                        Forms\Components\DateTimePicker::make('booking_end')
                            ->label('Ends')
                            ->required()
                            ->after('booking_start')
                            ->rules(['after:booking_start'])
                            ->helperText('Can be the same date as Starts; the time must be later.')
                            ->validationMessages([
                                'after' => 'Ends must be after Starts (same date with a later time is allowed).',
                            ]),
                        Forms\Components\Select::make('status')
                            ->options([
                                'approved' => 'Approved',
                                'pending' => 'Pending',
                                'rejected' => 'Rejected',
                                'canceled' => 'Canceled',
                            ])
                            ->default('approved')
                            ->required(),
                        Forms\Components\Select::make('package_id')
                            ->label('Package')
                            ->relationship('package', 'title')
                            ->searchable()
                            ->preload()
                            ->hidden(true),
                        Forms\Components\Textarea::make('internal_notes')->label('Internal notes')->rows(2),
                    ])->columns(2),
                Section::make('Create on multiple dates')
                    ->description('Create several appointments with the same details on repeated weekdays (e.g. next 4 Mondays).')
                    ->visibleOn(Operation::Create)
                    ->schema([
                        Forms\Components\Toggle::make('create_recurring')
                            ->label('Create weekly on multiple dates')
                            ->default(false)
                            ->live(),
                        Forms\Components\Select::make('create_recurring_day')
                            ->label('Day of week')
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->required(fn (Get $get) => (bool) $get('create_recurring'))
                            ->visible(fn (Get $get) => (bool) $get('create_recurring')),
                        Forms\Components\TextInput::make('create_recurring_count')
                            ->label('Number of occurrences')
                            ->numeric()
                            ->minValue(2)
                            ->maxValue(52)
                            ->default(4)
                            ->required(fn (Get $get) => (bool) $get('create_recurring'))
                            ->visible(fn (Get $get) => (bool) $get('create_recurring'))
                            ->helperText('Creates this many separate appointments on the next N selected weekdays, starting from the start date above.'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
