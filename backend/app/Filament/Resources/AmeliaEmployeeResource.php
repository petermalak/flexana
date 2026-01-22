<?php

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\AmeliaEmployeeResource\Pages;

class AmeliaEmployeeResource extends Resource
{
    protected static ?string $model = AmeliaUserModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Amelia Employees';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('firstName')->required(),
            Forms\Components\TextInput::make('lastName')->required(),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('phone'),
            Forms\Components\Select::make('type')->options([
                'provider' => 'Provider',
                'manager' => 'Manager',
                'admin' => 'Admin',
            ])->required(),
            Forms\Components\Select::make('status')->options([
                'visible' => 'Visible',
                'hidden' => 'Hidden',
                'disabled' => 'Disabled',
                'blocked' => 'Blocked',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('firstName')->label('First Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('lastName')->label('Last Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'provider' => 'success',
                        'manager' => 'warning',
                        'admin' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'visible' => 'success',
                        'hidden' => 'gray',
                        'disabled' => 'danger',
                        'blocked' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'provider' => 'Provider',
                    'manager' => 'Manager',
                    'admin' => 'Admin',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    'visible' => 'Visible',
                    'hidden' => 'Hidden',
                    'disabled' => 'Disabled',
                    'blocked' => 'Blocked',
                ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->employees();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAmeliaEmployees::route('/'),
            'create' => Pages\CreateAmeliaEmployee::route('/create'),
            'edit' => Pages\EditAmeliaEmployee::route('/{record}/edit'),
        ];
    }
}

