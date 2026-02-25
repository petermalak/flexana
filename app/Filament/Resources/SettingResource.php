<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static \UnitEnum|string|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Setting')
                    ->schema([
                        Forms\Components\TextInput::make('group')->default('general')->maxLength(100)->required(),
                        Forms\Components\TextInput::make('key')->required()->maxLength(255)->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('value')->rows(3),
                        Forms\Components\Select::make('type')
                            ->options(['string' => 'String', 'boolean' => 'Boolean', 'integer' => 'Integer', 'json' => 'JSON'])
                            ->default('string')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('group')->searchable()->sortable()->badge(),
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('value')->limit(50)->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge()->toggleable(),
            ])
            ->defaultSort('group')
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
