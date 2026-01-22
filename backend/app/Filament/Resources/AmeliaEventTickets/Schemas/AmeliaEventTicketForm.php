<?php

namespace App\Filament\Resources\AmeliaEventTickets\Schemas;

use Filament\Schemas\Schema;

class AmeliaEventTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('eventId')
                    ->label('Event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('name')
                    ->required(),
                \Filament\Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->default(0)
                    ->required(),
                \Filament\Forms\Components\TextInput::make('spots')
                    ->numeric()
                    ->default(1)
                    ->required(),
                \Filament\Forms\Components\TextInput::make('waitingListSpots')
                    ->label('Waiting List Spots')
                    ->numeric()
                    ->default(0),
                \Filament\Forms\Components\Toggle::make('enabled')
                    ->default(true),
            ]);
    }
}
