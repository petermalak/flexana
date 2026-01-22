<?php

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\AmeliaBookingResource\Pages;

class AmeliaBookingResource extends Resource
{
    protected static ?string $model = AmeliaCustomerBookingModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Amelia Bookings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('appointmentId')
                    ->label('Appointment')
                    ->relationship('appointment', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        "Appointment #{$record->id} - " . ($record->bookingStart ? $record->bookingStart->format('Y-m-d H:i') : 'N/A')
                    )
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('customerId')
                    ->label('Customer')
                    ->relationship('customer', 'email')
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        trim("{$record->firstName} {$record->lastName} ({$record->email})")
                    )
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('status')
                    ->options([
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'canceled' => 'Canceled',
                        'rejected' => 'Rejected',
                        'no-show' => 'No Show',
                        'waiting' => 'Waiting',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('persons')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('duration')
                    ->numeric()
                    ->suffix('minutes'),
                Forms\Components\DateTimePicker::make('created')
                    ->label('Created At'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('appointment.bookingStart')
                    ->label('Appointment Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.firstName')
                    ->label('Customer')
                    ->formatStateUsing(fn ($record) => 
                        $record->customer ? trim("{$record->customer->firstName} {$record->customer->lastName}") : 'N/A'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'canceled' => 'danger',
                        'rejected' => 'danger',
                        'no-show' => 'gray',
                        'waiting' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('persons')
                    ->label('Persons')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'canceled' => 'Canceled',
                        'rejected' => 'Rejected',
                        'no-show' => 'No Show',
                        'waiting' => 'Waiting',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('created', 'desc');
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
            'index' => Pages\ListAmeliaBookings::route('/'),
            'create' => Pages\CreateAmeliaBooking::route('/create'),
            'edit' => Pages\EditAmeliaBooking::route('/{record}/edit'),
        ];
    }
}
