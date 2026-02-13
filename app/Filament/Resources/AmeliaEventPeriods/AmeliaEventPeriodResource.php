<?php

namespace App\Filament\Resources\AmeliaEventPeriods;

use App\Filament\Resources\AmeliaEventPeriods\Pages\CreateAmeliaEventPeriod;
use App\Filament\Resources\AmeliaEventPeriods\Pages\EditAmeliaEventPeriod;
use App\Filament\Resources\AmeliaEventPeriods\Pages\ListAmeliaEventPeriods;
use App\Filament\Resources\AmeliaEventPeriods\Schemas\AmeliaEventPeriodForm;
use App\Filament\Resources\AmeliaEventPeriods\Tables\AmeliaEventPeriodsTable;
use App\Models\AmeliaEventPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmeliaEventPeriodResource extends Resource
{
    protected static ?string $model = AmeliaEventPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Event Periods';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return AmeliaEventPeriodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmeliaEventPeriodsTable::configure($table);
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
            'index' => ListAmeliaEventPeriods::route('/'),
            'create' => CreateAmeliaEventPeriod::route('/create'),
            'edit' => EditAmeliaEventPeriod::route('/{record}/edit'),
        ];
    }
}
