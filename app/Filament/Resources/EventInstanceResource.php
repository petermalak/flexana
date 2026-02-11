<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventInstanceResource\Pages;
use App\Models\EventInstance;
use App\Models\Event;
use App\Models\Staff;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class EventInstanceResource extends Resource
{
    protected static ?string $model = EventInstance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Event')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->required()
                            ->timezone('UTC'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->required()
                            ->timezone('UTC')
                            ->after('starts_at'),
                        Forms\Components\TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('location')
                            ->maxLength(255),
                    ])->columns(2),
                Components\Section::make('Booking Window')
                    ->schema([
                        Forms\Components\DateTimePicker::make('booking_open_date')
                            ->label('Booking Opens')
                            ->timezone('UTC'),
                        Forms\Components\DateTimePicker::make('booking_close_date')
                            ->label('Booking Closes')
                            ->timezone('UTC')
                            ->after('booking_open_date'),
                    ])->columns(2),
                Components\Section::make('Instructor')
                    ->schema([
                        Forms\Components\Select::make('instructor_id')
                            ->label('Instructor')
                            ->relationship('instructor', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'scheduled' => 'Scheduled',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                            ])
                            ->searchable()
                            ->default('scheduled')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label('Instructor')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'scheduled',
                        'danger' => 'cancelled',
                        'info' => 'completed',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_open_date')
                    ->label('Booking Opens')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('booking_close_date')
                    ->label('Booking Closes')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'name'),
                Tables\Filters\Filter::make('starts_at')
                    ->form([
                        Forms\Components\DatePicker::make('starts_from')
                            ->label('Starts From'),
                        Forms\Components\DatePicker::make('starts_until')
                            ->label('Starts Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['starts_from'],
                                fn ($query, $date) => $query->whereDate('starts_at', '>=', $date),
                            )
                            ->when(
                                $data['starts_until'],
                                fn ($query, $date) => $query->whereDate('starts_at', '<=', $date),
                            );
                    }),
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
            'index' => Pages\ManageEventInstances::route('/'),
        ];
    }
}

