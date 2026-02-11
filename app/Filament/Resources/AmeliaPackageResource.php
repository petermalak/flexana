<?php

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\AmeliaPackageModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\AmeliaPackageResource\Pages;

class AmeliaPackageResource extends Resource
{
    protected static ?string $model = AmeliaPackageModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Amelia Packages';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Textarea::make('description')->rows(3),
            Forms\Components\TextInput::make('price')->numeric()->prefix('$')->required(),
            Forms\Components\TextInput::make('discount')->numeric()->prefix('$')->default(0),
            Forms\Components\Select::make('status')->options([
                'visible' => 'Visible',
                'hidden' => 'Hidden',
                'disabled' => 'Disabled',
            ]),
            Forms\Components\DateTimePicker::make('endDate')->label('End Date'),
            Forms\Components\TextInput::make('quantity')->numeric()->minValue(1)->default(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('discount')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'visible' => 'success',
                        'hidden' => 'gray',
                        'disabled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('endDate')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'visible' => 'Visible',
                    'hidden' => 'Hidden',
                    'disabled' => 'Disabled',
                ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAmeliaPackages::route('/'),
            'create' => Pages\CreateAmeliaPackage::route('/create'),
            'edit' => Pages\EditAmeliaPackage::route('/{record}/edit'),
        ];
    }
}

