<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static \UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Promo Code';

    protected static ?string $pluralModelLabel = 'Promo Codes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('name')
                            ->maxLength(255)
                            ->placeholder('Internal name (optional)'),
                        Forms\Components\TextInput::make('percent_discount')
                            ->label('Discount (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required()
                            ->suffix('%'),
                    ])->columns(2),
                Components\Section::make('Validity')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid from'),
                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label('Valid until'),
                        Forms\Components\TextInput::make('usage_limit')
                            ->label('Max uses')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Unlimited'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('percent_discount')
                    ->label('Discount %')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Used')
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('Limit')
                    ->placeholder('∞')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_from')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Use simple bulk delete action for compatibility with Filament version in production.
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
