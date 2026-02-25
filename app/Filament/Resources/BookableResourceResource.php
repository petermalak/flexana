<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookableResourceResource\Pages;
use App\Models\BookableResource;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class BookableResourceResource extends Resource
{
    protected static ?string $model = BookableResource::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static \UnitEnum|string|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Resources';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Bookable Resource';

    protected static ?string $pluralModelLabel = 'Resources';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Resource')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\Textarea::make('description')->rows(3),
                        Forms\Components\TextInput::make('quantity')->numeric()->default(1)->minValue(1),
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
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\IconColumn::make('status')->boolean()->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('status')])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBookableResources::route('/'),
            'create' => Pages\CreateBookableResource::route('/create'),
            'edit' => Pages\EditBookableResource::route('/{record}/edit'),
        ];
    }
}
