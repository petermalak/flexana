<?php

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\AmeliaServiceModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\AmeliaServiceResource\Pages;

class AmeliaServiceResource extends Resource
{
    protected static ?string $model = AmeliaServiceModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Amelia Services';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->rows(3),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('duration')
                    ->numeric()
                    ->suffix('minutes')
                    ->required(),
                Forms\Components\TextInput::make('minCapacity')
                    ->label('Min Capacity')
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('maxCapacity')
                    ->label('Max Capacity')
                    ->numeric()
                    ->default(1),
                Forms\Components\Select::make('status')
                    ->options([
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                        'disabled' => 'Disabled',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration (min)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('minCapacity')
                    ->label('Min Capacity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('maxCapacity')
                    ->label('Max Capacity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'visible' => 'success',
                        'hidden' => 'gray',
                        'disabled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('appointments_count')
                    ->label('Appointments')
                    ->counts('appointments')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                        'disabled' => 'Disabled',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAmeliaServices::route('/'),
            'create' => Pages\CreateAmeliaService::route('/create'),
            'edit' => Pages\EditAmeliaService::route('/{record}/edit'),
        ];
    }
}
