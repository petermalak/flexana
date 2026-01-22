<?php

namespace App\Filament\Resources\AmeliaAppointments;

use App\Filament\Resources\AmeliaAppointments\Pages\CreateAmeliaAppointment;
use App\Filament\Resources\AmeliaAppointments\Pages\EditAmeliaAppointment;
use App\Filament\Resources\AmeliaAppointments\Pages\ListAmeliaAppointments;
use App\Filament\Resources\AmeliaAppointments\Schemas\AmeliaAppointmentForm;
use App\Filament\Resources\AmeliaAppointments\Tables\AmeliaAppointmentsTable;
use App\Models\AmeliaAppointment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmeliaAppointmentResource extends Resource
{
    protected static ?string $model = AmeliaAppointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    
    protected static string|\UnitEnum|null $navigationGroup = 'Amelia Data';
    
    protected static ?int $navigationSort = 9;
    
    protected static ?string $navigationLabel = 'Amelia Appointments';

    public static function form(Schema $schema): Schema
    {
        return AmeliaAppointmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmeliaAppointmentsTable::configure($table);
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
            'index' => ListAmeliaAppointments::route('/'),
            'create' => CreateAmeliaAppointment::route('/create'),
            'edit' => EditAmeliaAppointment::route('/{record}/edit'),
        ];
    }
}
