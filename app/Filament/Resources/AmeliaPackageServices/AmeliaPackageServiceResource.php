<?php

namespace App\Filament\Resources\AmeliaPackageServices;

use App\Filament\Resources\AmeliaPackageServices\Pages\CreateAmeliaPackageService;
use App\Filament\Resources\AmeliaPackageServices\Pages\EditAmeliaPackageService;
use App\Filament\Resources\AmeliaPackageServices\Pages\ListAmeliaPackageServices;
use App\Filament\Resources\AmeliaPackageServices\Schemas\AmeliaPackageServiceForm;
use App\Filament\Resources\AmeliaPackageServices\Tables\AmeliaPackageServicesTable;
use App\Models\AmeliaPackageService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmeliaPackageServiceResource extends Resource
{
    protected static ?string $model = AmeliaPackageService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;
    
    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';
    
    protected static ?int $navigationSort = 8;
    
    protected static ?string $navigationLabel = 'Package Services';

    public static function form(Schema $schema): Schema
    {
        return AmeliaPackageServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmeliaPackageServicesTable::configure($table);
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
            'index' => ListAmeliaPackageServices::route('/'),
            'create' => CreateAmeliaPackageService::route('/create'),
            'edit' => EditAmeliaPackageService::route('/{record}/edit'),
        ];
    }
}
