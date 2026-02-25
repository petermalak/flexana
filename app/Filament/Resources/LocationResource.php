<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map-pin';

    protected static \UnitEnum|string|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Locations';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Location')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\Textarea::make('address')->rows(2),
                        Forms\Components\TextInput::make('phone')->maxLength(50),
                        Forms\Components\TextInput::make('latitude')->maxLength(50),
                        Forms\Components\TextInput::make('longitude')->maxLength(50),
                        Forms\Components\Toggle::make('status')->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('address')->limit(40)->toggleable(),
                Tables\Columns\IconColumn::make('status')->boolean()->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('status')])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
