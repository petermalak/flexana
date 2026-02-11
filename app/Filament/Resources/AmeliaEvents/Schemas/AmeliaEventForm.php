<?php

namespace App\Filament\Resources\AmeliaEvents\Schemas;

use Filament\Schemas\Schema;

class AmeliaEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('name')
                    ->required(),
                \Filament\Forms\Components\Textarea::make('description')
                    ->rows(3),
                \Filament\Forms\Components\Select::make('status')
                    ->options([
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'canceled' => 'Canceled',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                \Filament\Forms\Components\DateTimePicker::make('bookingOpens')
                    ->label('Booking Opens'),
                \Filament\Forms\Components\DateTimePicker::make('bookingCloses')
                    ->label('Booking Closes'),
                \Filament\Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                \Filament\Forms\Components\TextInput::make('maxCapacity')
                    ->label('Max Capacity')
                    ->numeric()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('maxExtraPeople')
                    ->label('Max Extra People')
                    ->numeric(),
                \Filament\Forms\Components\ColorPicker::make('color'),
                \Filament\Forms\Components\Toggle::make('show')
                    ->default(true),
                \Filament\Forms\Components\Toggle::make('notifyParticipants')
                    ->label('Notify Participants')
                    ->default(true),
            ]);
    }
}
