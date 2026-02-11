<?php

namespace App\Filament\Resources\AmeliaEventPeriods\Schemas;

use Filament\Schemas\Schema;

class AmeliaEventPeriodForm
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
                \Filament\Forms\Components\DateTimePicker::make('periodStart')
                    ->label('Period Start')
                    ->required(),
                \Filament\Forms\Components\DateTimePicker::make('periodEnd')
                    ->label('Period End')
                    ->required(),
                \Filament\Forms\Components\TextInput::make('googleCalendarEventId')
                    ->label('Google Calendar Event ID'),
                \Filament\Forms\Components\TextInput::make('googleMeetUrl')
                    ->label('Google Meet URL')
                    ->url(),
                \Filament\Forms\Components\TextInput::make('outlookCalendarEventId')
                    ->label('Outlook Calendar Event ID'),
                \Filament\Forms\Components\TextInput::make('microsoftTeamsUrl')
                    ->label('Microsoft Teams URL')
                    ->url(),
                \Filament\Forms\Components\TextInput::make('appleCalendarEventId')
                    ->label('Apple Calendar Event ID'),
            ]);
    }
}
