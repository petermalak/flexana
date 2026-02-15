<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventPeriodResource\Pages;
use App\Models\EventPeriod;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class EventPeriodResource extends Resource
{
    protected static ?string $model = EventPeriod::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static \UnitEnum|string|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Event Periods';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Event Period')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\DateTimePicker::make('period_start')->required(),
                        Forms\Components\DateTimePicker::make('period_end')->required(),
                        Forms\Components\TextInput::make('google_meet_url')->url()->maxLength(500),
                        Forms\Components\TextInput::make('microsoft_teams_url')->url()->maxLength(500),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('event.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('period_start')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('period_end')->dateTime()->sortable(),
            ])
            ->defaultSort('period_start', 'desc')
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEventPeriods::route('/'),
            'create' => Pages\CreateEventPeriod::route('/create'),
            'edit' => Pages\EditEventPeriod::route('/{record}/edit'),
        ];
    }
}
