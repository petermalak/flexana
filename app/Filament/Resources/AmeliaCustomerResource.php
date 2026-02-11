<?php

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\CustomerModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\AmeliaCustomerResource\Pages;

class AmeliaCustomerResource extends Resource
{
    protected static ?string $model = CustomerModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Amelia Customers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('first_name')
                    ->label('First Name')
                    ->required(),
                Forms\Components\TextInput::make('last_name')
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->counts('bookings')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                //
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
}
