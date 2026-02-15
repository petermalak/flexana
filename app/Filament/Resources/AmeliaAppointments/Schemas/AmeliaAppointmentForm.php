<?php

namespace App\Filament\Resources\AmeliaAppointments\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms;

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
                        Forms\Components\DateTimePicker::make('booking_end')->label('Ends')->required()->after('booking_start'),
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
            ]);
    }
}
