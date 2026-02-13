<?php

namespace App\Filament\Resources\AmeliaEventTickets;

use App\Filament\Resources\AmeliaEventTickets\Pages\CreateAmeliaEventTicket;
use App\Filament\Resources\AmeliaEventTickets\Pages\EditAmeliaEventTicket;
use App\Filament\Resources\AmeliaEventTickets\Pages\ListAmeliaEventTickets;
use App\Filament\Resources\AmeliaEventTickets\Schemas\AmeliaEventTicketForm;
use App\Filament\Resources\AmeliaEventTickets\Tables\AmeliaEventTicketsTable;
use App\Models\AmeliaEventTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmeliaEventTicketResource extends Resource
{
    protected static ?string $model = AmeliaEventTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Event Tickets';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return AmeliaEventTicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmeliaEventTicketsTable::configure($table);
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
            'index' => ListAmeliaEventTickets::route('/'),
            'create' => CreateAmeliaEventTicket::route('/create'),
            'edit' => EditAmeliaEventTicket::route('/{record}/edit'),
        ];
    }
}
