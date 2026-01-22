<?php

namespace App\Filament\Resources\AmeliaProviderServices\Schemas;

use Filament\Schemas\Schema;

class AmeliaProviderServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('userId')
                    ->label('Employee')
                    ->relationship('provider', 'email')
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        trim("{$record->firstName} {$record->lastName} ({$record->email})")
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                \Filament\Forms\Components\Select::make('serviceId')
                    ->label('Service')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                \Filament\Forms\Components\TextInput::make('minCapacity')
                    ->label('Min Capacity')
                    ->numeric()
                    ->default(1)
                    ->required(),
                \Filament\Forms\Components\TextInput::make('maxCapacity')
                    ->label('Max Capacity')
                    ->numeric()
                    ->default(1)
                    ->required(),
            ]);
    }
}
