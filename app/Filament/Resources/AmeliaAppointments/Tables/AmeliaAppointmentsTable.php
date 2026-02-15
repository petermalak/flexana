<?php

namespace App\Filament\Resources\AmeliaAppointments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AmeliaAppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('service.name')->label('Service')->searchable()->sortable(),
                TextColumn::make('provider.name')->label('Instructor')->searchable()->sortable(),
                TextColumn::make('booking_start')->label('Starts')->dateTime()->sortable(),
                TextColumn::make('booking_end')->label('Ends')->dateTime()->sortable()->toggleable(),
                TextColumn::make('service.time_before')->label('Mins before cancel')->sortable()->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->defaultSort('booking_start', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
