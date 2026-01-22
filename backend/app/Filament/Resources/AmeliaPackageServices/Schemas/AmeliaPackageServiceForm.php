<?php

namespace App\Filament\Resources\AmeliaPackageServices\Schemas;

use Filament\Schemas\Schema;

class AmeliaPackageServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('packageId')
                    ->label('Package')
                    ->relationship('package', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                \Filament\Forms\Components\Select::make('serviceId')
                    ->label('Service')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required(),
                \Filament\Forms\Components\TextInput::make('minimumScheduled')
                    ->label('Min Scheduled')
                    ->numeric()
                    ->default(1),
                \Filament\Forms\Components\TextInput::make('maximumScheduled')
                    ->label('Max Scheduled')
                    ->numeric()
                    ->default(1),
                \Filament\Forms\Components\Toggle::make('allowProviderSelection')
                    ->label('Allow Provider Selection')
                    ->default(true),
                \Filament\Forms\Components\TextInput::make('position')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
