<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Event;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('event_id')
                    ->label('Event')
                    ->searchable()
                    ->relationship('event', 'name')
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->searchable()
                    ->relationship('customer', 'first_name')
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => trim("{$record->first_name} {$record->last_name}"))
                    ->required(),
                Forms\Components\TextInput::make('party_size')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->label('Spots (persons)')
                    ->helperText('Number of persons for this booking.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show',
                    ])
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'refunded' => 'Refunded',
                    ])
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->prefix('$')
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('deposit_amount')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),
                Forms\Components\Textarea::make('notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($record) => trim("{$record->customer?->first_name} {$record->customer?->last_name}"))
                    ->sortable()
                    // Important: when this column is already scoped to the `customer` relationship
                    // (via `customer.first_name`), Filament expects plain related-table columns
                    // (`first_name/last_name/email`). Using `customer.*` makes Filament treat it as
                    // a JSON column and generates SQL like json_extract(`customer`, ...).
                    ->searchable(['first_name', 'last_name', 'email']),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'info' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'info' => 'partial',
                        'danger' => 'refunded',
                    ])
                    ->label('Payment'),
                Tables\Columns\TextColumn::make('party_size')
                    ->label('Spots')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_drop_in')
                    ->label('Drop-in')
                    ->boolean()
                    ->trueIcon('heroicon-o-ticket')
                    ->falseIcon('heroicon-o-cube')
                    ->trueColor('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booked_at')
                    ->dateTime('Y-m-d H:i')
                    // Explicitly qualify the column for sorting because the table
                    // can join related tables for search/sort, making unqualified
                    // ordering ambiguous.
                    ->sortable(query: fn ($query, $direction) => $query
                        ->orderBy('bookings.booked_at', $direction)
                        // Tie-breaker: keep ordering stable when timestamps match.
                        ->orderBy('bookings.id', $direction)),
            ])
            // Ensure deterministic ordering, especially when search causes
            // related table joins.
            ->defaultSort('booked_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\TernaryFilter::make('is_drop_in')
                    ->label('Drop-in')
                    ->placeholder('All')
                    ->trueLabel('Drop-in only')
                    ->falseLabel('Package only'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBookings::route('/'),
        ];
    }
}

