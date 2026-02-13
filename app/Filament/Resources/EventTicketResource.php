<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventTicketResource\Pages;
use App\Models\EventTicket;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class EventTicketResource extends Resource
{
    protected static ?string $model = EventTicket::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static \UnitEnum|string|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Event Tickets';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Event Ticket')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('price')->numeric()->prefix('$')->default(0),
                        Forms\Components\TextInput::make('spots')->numeric()->nullable(),
                        Forms\Components\TextInput::make('waiting_list_spots')->numeric()->default(0),
                        Forms\Components\Toggle::make('enabled')->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('event.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('spots')->sortable(),
                Tables\Columns\IconColumn::make('enabled')->boolean()->sortable(),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEventTickets::route('/'),
            'create' => Pages\CreateEventTicket::route('/create'),
            'edit' => Pages\EditEventTicket::route('/{record}/edit'),
        ];
    }
}
