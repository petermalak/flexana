<?php

namespace App\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\BookingModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\AmeliaBookingResource\Pages;

class AmeliaBookingResource extends Resource
{
    protected static ?string $model = BookingModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Amelia Bookings';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('appointment_id')
                    ->label('Appointment')
                    ->relationship('appointment', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) =>
                        $record ? 'Appointment #' . $record->id . ' - ' . ($record->booking_start ? $record->booking_start->format('Y-m-d H:i') : 'N/A') : 'N/A'
                    )
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'email')
                    ->getOptionLabelFromRecordUsing(fn ($record) =>
                        $record ? trim("{$record->first_name} {$record->last_name} ({$record->email})") : 'N/A'
                    )
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('party_size')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->label('Party size (total seats)'),
                Forms\Components\TextInput::make('spots')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->label('Extra spots')
                    ->helperText('Drop-in only: additional seats beyond the base party; total seats should match party size.'),
                Forms\Components\DateTimePicker::make('booked_at')
                    ->label('Booked At'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('appointment.booking_start')
                    ->label('Appointment Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($record) =>
                        $record->customer ? trim("{$record->customer->first_name} {$record->customer->last_name}") : 'N/A'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('party_size')
                    ->label('Party size')
                    ->sortable(),
                Tables\Columns\TextColumn::make('spots')
                    ->label('Extra spots')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('booked_at')
                    ->label('Booked')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('booked_at', 'desc');
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
