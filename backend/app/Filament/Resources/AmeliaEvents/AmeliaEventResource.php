<?php

namespace App\Filament\Resources\AmeliaEvents;

use App\Filament\Resources\AmeliaEvents\Pages\CreateAmeliaEvent;
use App\Filament\Resources\AmeliaEvents\Pages\EditAmeliaEvent;
use App\Filament\Resources\AmeliaEvents\Pages\ListAmeliaEvents;
use App\Filament\Resources\AmeliaEvents\Schemas\AmeliaEventForm;
use App\Filament\Resources\AmeliaEvents\Tables\AmeliaEventsTable;
use App\Models\AmeliaEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmeliaEventResource extends Resource
{
    protected static ?string $model = AmeliaEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;
    
    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';
    
    protected static ?int $navigationSort = 4;
    
    protected static ?string $navigationLabel = 'Amelia Events';

    public static function form(Schema $schema): Schema
    {
        return AmeliaEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmeliaEventsTable::configure($table);
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
            'index' => ListAmeliaEvents::route('/'),
            'create' => CreateAmeliaEvent::route('/create'),
            'edit' => EditAmeliaEvent::route('/{record}/edit'),
        ];
    }
}
