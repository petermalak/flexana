<?php

namespace App\Filament\Resources\AmeliaAppointments\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;

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
                        Forms\Components\DateTimePicker::make('booking_start')->label('Starts')->required(),
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
                            ->preload(),
                        Forms\Components\Textarea::make('internal_notes')->label('Internal notes')->rows(2),
                    ])->columns(2),
                Section::make('Create on multiple dates')
                    ->description('Create several appointments with the same details on repeated weekdays (e.g. next 4 Mondays).')
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
