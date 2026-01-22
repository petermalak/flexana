<?php

namespace App\Filament\Resources\AmeliaProviderServices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class AmeliaProviderServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('provider.firstName')
                    ->label('Employee')
                    ->formatStateUsing(fn ($record) => 
                        $record->provider ? trim("{$record->provider->firstName} {$record->provider->lastName}") : 'N/A'
                    )
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('minCapacity')
                    ->label('Min Capacity')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('maxCapacity')
                    ->label('Max Capacity')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('userId')
                    ->label('Employee')
                    ->relationship('provider', 'email')
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        trim("{$record->firstName} {$record->lastName}")
                    )
                    ->searchable()
                    ->preload(),
                \Filament\Tables\Filters\SelectFilter::make('serviceId')
                    ->label('Service')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload(),
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
