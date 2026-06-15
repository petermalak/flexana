<?php

namespace App\Filament\Resources\AmeliaAppointments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
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
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('Default')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('booking_start')
                    ->label('Starts')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('booking_end')
                    ->label('Ends')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('service.time_before')->label('Mins before cancel')->sortable()->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('recurrence_group_id')
                    ->label('Recurrence group')
                    ->formatStateUsing(fn (?string $state) => $state ? 'Yes (' . \Illuminate\Support\Str::limit($state, 8) . '…)' : '—')
                    ->sortable()
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->recurrence_group_id ? 'Group: ' . $record->recurrence_group_id : null),
            ])
            ->defaultSort('booking_start', 'desc')
            ->filters([
                TernaryFilter::make('recurrence_group_id')
                    ->label('Recurrence group')
                    ->nullable()
                    ->placeholder('All')
                    ->trueLabel('Part of recurring group')
                    ->falseLabel('Single appointment'),
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
