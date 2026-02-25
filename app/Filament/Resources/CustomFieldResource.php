<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomFieldResource\Pages;
use App\Models\CustomField;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class CustomFieldResource extends Resource
{
    protected static ?string $model = CustomField::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Custom Fields';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Custom Field')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('label')->required()->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Textarea',
                                'number' => 'Number',
                                'select' => 'Select',
                                'checkbox' => 'Checkbox',
                                'email' => 'Email',
                                'url' => 'URL',
                            ])
                            ->default('text')
                            ->required(),
                        Forms\Components\Select::make('entity_type')
                            ->options(['booking' => 'Booking', 'customer' => 'Customer', 'event' => 'Event'])
                            ->default('booking'),
                        Forms\Components\Toggle::make('required')->default(false),
                        Forms\Components\Textarea::make('options')
                            ->helperText('For select type: JSON array e.g. ["Option A","Option B"]')
                            ->rows(2),
                        Forms\Components\TextInput::make('position')->numeric()->default(0),
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
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('entity_type')->badge(),
                Tables\Columns\IconColumn::make('required')->boolean(),
                Tables\Columns\IconColumn::make('status')->boolean()->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('status')])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCustomFields::route('/'),
            'create' => Pages\CreateCustomField::route('/create'),
            'edit' => Pages\EditCustomField::route('/{record}/edit'),
        ];
    }
}
