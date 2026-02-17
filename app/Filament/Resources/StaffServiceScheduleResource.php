<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffServiceScheduleResource\Pages;
use App\Infrastructure\Persistence\Eloquent\StaffServiceScheduleModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class StaffServiceScheduleResource extends Resource
{
    protected static ?string $model = StaffServiceScheduleModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Staff service schedules';

    protected static ?string $modelLabel = 'Service schedule';

    protected static ?string $pluralModelLabel = 'Service schedules';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Assignment')
                    ->description('Select the staff member and service for this schedule.')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        Forms\Components\Select::make('staff_id')
                            ->label('Staff member')
                            ->relationship('staff', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2),
                Components\Section::make('Schedule')
                    ->description('Set when and how often this service is available.')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        Forms\Components\Select::make('recurrence_type')
                            ->label('Recurrence')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->default('weekly')
                            ->required()
                            ->live()
                            ->helperText('How often this schedule repeats'),
                        Forms\Components\Select::make('day_of_week')
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
                            ->visible(fn (Get $get) => $get('recurrence_type') === 'weekly')
                            ->required(fn (Get $get) => $get('recurrence_type') === 'weekly')
                            ->helperText('Required for weekly recurrence'),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start time')
                            ->required()
                            ->seconds(false)
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        StaffServiceScheduleResource::validateScheduleConflict($get, $value, $get('end_time'), $fail);
                                    };
                                },
                            ]),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('End time')
                            ->required()
                            ->seconds(false)
                            ->after('start_time')
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        StaffServiceScheduleResource::validateScheduleConflict($get, $get('start_time'), $value, $fail);
                                    };
                                },
                            ]),
                    ])->columns(2),
                Components\Section::make('Date range')
                    ->description('When this schedule is active.')
                    ->icon(Heroicon::OutlinedCalendar)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start date')
                            ->required()
                            ->default(now())
                            ->helperText('First day this schedule applies')
                            ->live()
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $staffId = $get('staff_id');
                                        if (!$staffId || !$value) {
                                            return;
                                        }

                                        $date = \Carbon\Carbon::parse($value);

                                        // Check if start date is a staff off day
                                        $staffOffDay = \App\Infrastructure\Persistence\Eloquent\StaffOffDayModel::query()
                                            ->where('staff_id', $staffId)
                                            ->whereDate('date', $date->format('Y-m-d'))
                                            ->first();

                                        if ($staffOffDay) {
                                            $fail("Staff member has an off day on {$date->format('Y-m-d')}. Please choose a different start date.");
                                            return;
                                        }

                                        // Check if start date is a company holiday
                                        $companyHoliday = \App\Infrastructure\Persistence\Eloquent\CompanyOffDayModel::query()
                                            ->where('is_active', true)
                                            ->whereDate('date', $date->format('Y-m-d'))
                                            ->first();

                                        if ($companyHoliday) {
                                            $fail("Company holiday ({$companyHoliday->name}) on {$date->format('Y-m-d')}. Please choose a different start date.");
                                            return;
                                        }

                                        // For weekly schedules, check if the day_of_week matches any off days in the next 3 months
                                        $recurrenceType = $get('recurrence_type');
                                        $dayOfWeek = $get('day_of_week');
                                        if ($recurrenceType === 'weekly' && $dayOfWeek) {
                                            $dayMap = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0];
                                            $targetDay = $dayMap[$dayOfWeek] ?? null;
                                            if ($targetDay !== null) {
                                                $checkDate = $date->copy();
                                                $endCheck = $checkDate->copy()->addMonths(3);
                                                $conflicts = [];

                                                while ($checkDate->lte($endCheck)) {
                                                    if ($checkDate->dayOfWeek === $targetDay) {
                                                        $offDay = \App\Infrastructure\Persistence\Eloquent\StaffOffDayModel::query()
                                                            ->where('staff_id', $staffId)
                                                            ->whereDate('date', $checkDate->format('Y-m-d'))
                                                            ->first();

                                                        if ($offDay) {
                                                            $conflicts[] = $checkDate->format('Y-m-d');
                                                        }

                                                        $holiday = \App\Infrastructure\Persistence\Eloquent\CompanyOffDayModel::query()
                                                            ->where('is_active', true)
                                                            ->whereDate('date', $checkDate->format('Y-m-d'))
                                                            ->first();

                                                        if ($holiday) {
                                                            $conflicts[] = $checkDate->format('Y-m-d') . ' (' . $holiday->name . ')';
                                                        }
                                                    }
                                                    $checkDate->addDay();
                                                }

                                                if (!empty($conflicts)) {
                                                    $conflictList = implode(', ', array_slice($conflicts, 0, 5));
                                                    if (count($conflicts) > 5) {
                                                        $conflictList .= ' and ' . (count($conflicts) - 5) . ' more';
                                                    }
                                                    $fail("This weekly schedule conflicts with off days/holidays on: {$conflictList}. Please adjust the schedule dates.");
                                                }
                                            }
                                        }
                                    };
                                },
                            ]),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End date')
                            ->nullable()
                            ->after('start_date')
                            ->live()
                            ->helperText('Leave empty for ongoing schedule')
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if (!$value) {
                                            return; // End date is optional
                                        }

                                        $staffId = $get('staff_id');
                                        $startDate = $get('start_date');
                                        if (!$staffId || !$startDate) {
                                            return;
                                        }

                                        $endDate = \Carbon\Carbon::parse($value);

                                        // Check if end date is a staff off day
                                        $staffOffDay = \App\Infrastructure\Persistence\Eloquent\StaffOffDayModel::query()
                                            ->where('staff_id', $staffId)
                                            ->whereDate('date', $endDate->format('Y-m-d'))
                                            ->first();

                                        if ($staffOffDay) {
                                            $fail("Staff member has an off day on {$endDate->format('Y-m-d')}. Please choose a different end date.");
                                            return;
                                        }

                                        // Check if end date is a company holiday
                                        $companyHoliday = \App\Infrastructure\Persistence\Eloquent\CompanyOffDayModel::query()
                                            ->where('is_active', true)
                                            ->whereDate('date', $endDate->format('Y-m-d'))
                                            ->first();

                                        if ($companyHoliday) {
                                            $fail("Company holiday ({$companyHoliday->name}) on {$endDate->format('Y-m-d')}. Please choose a different end date.");
                                        }
                                    };
                                },
                            ]),
                    ])->columns(2),
                Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : 'Daily')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('End')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->placeholder('Ongoing')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('recurrence_type')
                    ->colors([
                        'primary' => 'daily',
                        'success' => 'weekly',
                        'info' => 'monthly',
                    ])
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('staff_id')
                    ->relationship('staff', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('service_id')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('recurrence_type')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStaffServiceSchedules::route('/'),
        ];
    }

    /**
     * Validate if the staff member has any conflicting schedules at the given time.
     */
    private static function validateScheduleConflict(Get $get, ?string $startTime, ?string $endTime, \Closure $fail): void
    {
        $staffId = $get('staff_id');
        $startDate = $get('start_date');
        $endDate = $get('end_date');
        $recurrenceType = $get('recurrence_type');
        $dayOfWeek = $get('day_of_week');
        $currentRecordId = $get('id'); // For edit mode, exclude current record

        if (!$staffId || !$startDate || !$startTime || !$endTime) {
            return;
        }

        // Parse times (handle both string and Carbon instances)
        $startTimeObj = is_string($startTime) ? \Carbon\Carbon::parse($startTime) : $startTime;
        $endTimeObj = is_string($endTime) ? \Carbon\Carbon::parse($endTime) : $endTime;

        // Get all existing schedules for this staff member
        $existingSchedules = \App\Infrastructure\Persistence\Eloquent\StaffServiceScheduleModel::query()
            ->where('staff_id', $staffId)
            ->where('is_active', true)
            ->when($currentRecordId, fn ($q) => $q->where('id', '!=', $currentRecordId))
            ->with('service')
            ->get();

        foreach ($existingSchedules as $schedule) {
            $scheduleStartDate = \Carbon\Carbon::parse($schedule->start_date);
            $scheduleEndDate = $schedule->end_date ? \Carbon\Carbon::parse($schedule->end_date) : null;
            $newStartDate = \Carbon\Carbon::parse($startDate);
            $newEndDate = $endDate ? \Carbon\Carbon::parse($endDate) : null;

            // Check if date ranges overlap
            $dateOverlap = false;
            if ($scheduleEndDate && $newEndDate) {
                $dateOverlap = $newStartDate->lte($scheduleEndDate) && $newEndDate->gte($scheduleStartDate);
            } elseif ($scheduleEndDate) {
                $dateOverlap = $newStartDate->lte($scheduleEndDate);
            } elseif ($newEndDate) {
                $dateOverlap = $newEndDate->gte($scheduleStartDate);
            } else {
                $dateOverlap = true; // Both ongoing
            }

            if (!$dateOverlap) {
                continue;
            }

            // Parse schedule times (handle TIME column format)
            $scheduleStartTime = $schedule->start_time instanceof \Carbon\Carbon
                ? $schedule->start_time
                : \Carbon\Carbon::parse($schedule->start_time);
            $scheduleEndTime = $schedule->end_time instanceof \Carbon\Carbon
                ? $schedule->end_time
                : \Carbon\Carbon::parse($schedule->end_time);

            // Extract time components for comparison
            $newStartHour = (int) $startTimeObj->format('H');
            $newStartMinute = (int) $startTimeObj->format('i');
            $newEndHour = (int) $endTimeObj->format('H');
            $newEndMinute = (int) $endTimeObj->format('i');
            $scheduleStartHour = (int) $scheduleStartTime->format('H');
            $scheduleStartMinute = (int) $scheduleStartTime->format('i');
            $scheduleEndHour = (int) $scheduleEndTime->format('H');
            $scheduleEndMinute = (int) $scheduleEndTime->format('i');

            // Convert to minutes for easier comparison
            $newStartMinutes = $newStartHour * 60 + $newStartMinute;
            $newEndMinutes = $newEndHour * 60 + $newEndMinute;
            $scheduleStartMinutes = $scheduleStartHour * 60 + $scheduleStartMinute;
            $scheduleEndMinutes = $scheduleEndHour * 60 + $scheduleEndMinute;

            // Check time overlap based on recurrence type
            $timeOverlap = false;

            if ($recurrenceType === 'daily' && $schedule->recurrence_type === 'daily') {
                // Daily schedules: check if times overlap
                $timeOverlap = $newStartMinutes < $scheduleEndMinutes && $newEndMinutes > $scheduleStartMinutes;
            } elseif ($recurrenceType === 'weekly' && $schedule->recurrence_type === 'weekly') {
                // Weekly schedules: check if same day of week and times overlap
                if ($dayOfWeek === $schedule->day_of_week) {
                    $timeOverlap = $newStartMinutes < $scheduleEndMinutes && $newEndMinutes > $scheduleStartMinutes;
                }
            } elseif ($recurrenceType === 'monthly' && $schedule->recurrence_type === 'monthly') {
                // Monthly schedules: check if times overlap (same day of month is implicit in date overlap)
                $timeOverlap = $newStartMinutes < $scheduleEndMinutes && $newEndMinutes > $scheduleStartMinutes;
            } else {
                // Mixed recurrence types: check all occurrences in overlapping date range
                $checkStart = max($newStartDate, $scheduleStartDate);
                $checkEnd = min($newEndDate ?? $checkStart->copy()->addYear(), $scheduleEndDate ?? $checkStart->copy()->addYear());

                if ($recurrenceType === 'weekly' && $schedule->recurrence_type === 'daily') {
                    // Weekly vs Daily: check if weekly day matches daily schedule
                    $dayMap = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0];
                    $targetDay = $dayMap[$dayOfWeek] ?? null;
                    if ($targetDay !== null) {
                        $current = $checkStart->copy();
                        while ($current->lte($checkEnd) && !$timeOverlap) {
                            if ($current->dayOfWeek === $targetDay) {
                                if ($newStartMinutes < $scheduleEndMinutes && $newEndMinutes > $scheduleStartMinutes) {
                                    $timeOverlap = true;
                                    break;
                                }
                            }
                            $current->addDay();
                        }
                    }
                } elseif ($recurrenceType === 'daily' && $schedule->recurrence_type === 'weekly') {
                    // Daily vs Weekly: check if weekly day matches
                    $dayMap = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0];
                    $targetDay = $dayMap[$schedule->day_of_week] ?? null;
                    if ($targetDay !== null) {
                        $current = $checkStart->copy();
                        while ($current->lte($checkEnd) && !$timeOverlap) {
                            if ($current->dayOfWeek === $targetDay) {
                                if ($newStartMinutes < $scheduleEndMinutes && $newEndMinutes > $scheduleStartMinutes) {
                                    $timeOverlap = true;
                                    break;
                                }
                            }
                            $current->addDay();
                        }
                    }
                }
            }

            if ($timeOverlap) {
                $serviceName = $schedule->service->name ?? 'Unknown';
                $conflictInfo = "Service: {$serviceName}";
                if ($schedule->recurrence_type === 'weekly' && $schedule->day_of_week) {
                    $conflictInfo .= ", Day: " . ucfirst($schedule->day_of_week);
                }
                $scheduleTimeStr = $scheduleStartTime->format('H:i') . ' - ' . $scheduleEndTime->format('H:i');
                $conflictInfo .= ", Time: {$scheduleTimeStr}";
                $fail("Staff member already has a conflicting schedule ({$conflictInfo}). Please choose a different time or date range.");
                return;
            }
        }
    }
}
