<?php

namespace App\Filament\Resources\AmeliaPackageServices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class AmeliaPackageServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('minimumScheduled')
                    ->label('Min Scheduled')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('maximumScheduled')
                    ->label('Max Scheduled')
                    ->sortable(),
                \Filament\Tables\Columns\IconColumn::make('allowProviderSelection')
                    ->label('Provider Selection')
                    ->boolean(),
                \Filament\Tables\Columns\TextColumn::make('position')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('packageId')
                    ->label('Package')
                    ->relationship('package', 'name')
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
