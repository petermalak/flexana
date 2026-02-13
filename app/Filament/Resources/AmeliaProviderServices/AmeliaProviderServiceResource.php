<?php

namespace App\Filament\Resources\AmeliaProviderServices;

use App\Filament\Resources\AmeliaProviderServices\Pages\CreateAmeliaProviderService;
use App\Filament\Resources\AmeliaProviderServices\Pages\EditAmeliaProviderService;
use App\Filament\Resources\AmeliaProviderServices\Pages\ListAmeliaProviderServices;
use App\Filament\Resources\AmeliaProviderServices\Schemas\AmeliaProviderServiceForm;
use App\Filament\Resources\AmeliaProviderServices\Tables\AmeliaProviderServicesTable;
use App\Models\AmeliaProviderService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmeliaProviderServiceResource extends Resource
{
    protected static ?string $model = AmeliaProviderService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Employee Services';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return AmeliaProviderServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmeliaProviderServicesTable::configure($table);
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
            'index' => ListAmeliaProviderServices::route('/'),
            'create' => CreateAmeliaProviderService::route('/create'),
            'edit' => EditAmeliaProviderService::route('/{record}/edit'),
        ];
    }
}
