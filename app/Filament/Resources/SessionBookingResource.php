<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SessionBookingResource\Pages;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SessionBookingResource extends Resource
{
    protected static ?string $model = BookingModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Session bookings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('appointment_id')
                ->label('Session')
                ->required()
                ->options(function () {
                    return AppointmentModel::query()
                        ->with(['service', 'provider'])
                        ->orderByDesc('booking_start')
                        ->limit(500)
                        ->get()
                        ->mapWithKeys(function (AppointmentModel $a) {
                            $service = $a->service?->name ?? 'Unknown';
                            $provider = $a->provider?->name ?? '';
                            $dt = $a->booking_start ? $a->booking_start->format('Y-m-d H:i') : 'N/A';
                            $label = trim("{$service} — {$dt}" . ($provider !== '' ? " — {$provider}" : ''));
                            return [$a->id => "#{$a->id} {$label}"];
                        })
                        ->all();
                })
                ->searchable(),

            Forms\Components\Select::make('customer_id')
                ->label('Customer')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    $search = trim($search);
                    if ($search === '') {
                        return [];
                    }

                    return CustomerModel::query()
                        ->where(function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ["%{$search}%"])
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orderBy('first_name')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (CustomerModel $c) => [$c->id => self::customerOptionLabel($c)])
                        ->all();
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    if ($value === null || $value === '') {
                        return null;
                    }
                    $c = CustomerModel::query()->find($value);
                    return $c ? self::customerOptionLabel($c) : null;
                }),

            Forms\Components\TextInput::make('spots')
                ->label('Spots (persons)')
                ->numeric()
                ->minValue(1)
                ->maxValue(20)
                ->default(1)
                ->required(),

            Forms\Components\Toggle::make('isDropIn')
                ->label('Drop-in')
                ->default(true)
                ->helperText('On = drop-in pricing. Off = deduct from an active package.'),

            Forms\Components\TextInput::make('promoCode')
                ->label('Promo code')
                ->maxLength(64)
                ->helperText('Drop-in only. Invalid code will be ignored.'),
        ]);
    }

    private static function customerOptionLabel(CustomerModel $c): string
    {
        $name = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
        $phone = trim((string) ($c->phone ?? ''));
        if ($phone !== '') {
            $phone = preg_replace('/\s+/', ' ', $phone) ?? $phone;
        }

        if ($name === '' && $phone === '') {
            return (string) $c->id;
        }

        if ($name !== '' && $phone !== '') {
            return "{$name} — {$phone}";
        }

        return $name !== '' ? $name : $phone;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('appointment.booking_start')
                    ->label('Session start')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('appointment.service.name')
                    ->label('Service')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(function (BookingModel $record): string {
                        $customer = $record->customer;
                        if (! $customer) {
                            return '-';
                        }

                        $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                        $phone = trim((string) ($customer->phone ?? ''));
                        $email = trim((string) ($customer->email ?? ''));

                        if ($name !== '' && $phone !== '') {
                            return "{$name} — {$phone}";
                        }
                        if ($name !== '' && $email !== '') {
                            return "{$name} — {$email}";
                        }

                        return $name !== '' ? $name : ($phone !== '' ? $phone : ($email !== '' ? $email : (string) $customer->id));
                    })
                    ->searchable(query: function ($query, string $search) {
                        $search = trim($search);
                        if ($search === '') {
                            return $query;
                        }

                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ["%{$search}%"])
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('party_size')
                    ->label('Spots')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_drop_in')
                    ->label('Drop-in')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('booked_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('booked_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSessionBookings::route('/'),
        ];
    }
}

