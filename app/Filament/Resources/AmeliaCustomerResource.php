<?php

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\AmeliaCustomerResource\Pages;

class AmeliaCustomerResource extends Resource
{
    protected static ?string $model = AmeliaUserModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Amelia Customers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('firstName')
                    ->label('First Name')
                    ->required(),
                Forms\Components\TextInput::make('lastName')
                    ->label('Last Name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\Select::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),
                Forms\Components\DatePicker::make('birthday'),
                Forms\Components\Select::make('status')
                    ->options([
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                    ]),
                Forms\Components\Select::make('type')
                    ->options([
                        'customer' => 'Customer',
                        'provider' => 'Provider',
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
                Tables\Columns\TextColumn::make('firstName')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lastName')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'customer' => 'info',
                        'provider' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'visible' => 'success',
                        'hidden' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('customerBookings_count')
                    ->label('Bookings')
                    ->counts('customerBookings')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'customer' => 'Customer',
                        'provider' => 'Provider',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListAmeliaCustomers::route('/'),
            'create' => Pages\CreateAmeliaCustomer::route('/create'),
            'edit' => Pages\EditAmeliaCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('type', 'customer');
    }
}
