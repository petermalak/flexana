<?php

namespace App\Filament\Resources\AmeliaEvents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class AmeliaEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'canceled' => 'danger',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                \Filament\Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('maxCapacity')
                    ->label('Max Capacity')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('bookingOpens')
                    ->label('Booking Opens')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('bookingCloses')
                    ->label('Booking Closes')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                \Filament\Tables\Columns\IconColumn::make('show')
                    ->boolean(),
                \Filament\Tables\Columns\TextColumn::make('created')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'canceled' => 'Canceled',
                        'rejected' => 'Rejected',
                    ]),
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
