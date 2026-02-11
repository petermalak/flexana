<?php

namespace App\Filament\Resources\AmeliaAppointments\Pages;

use App\Filament\Resources\AmeliaAppointments\AmeliaAppointmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaAppointments extends ListRecords
{
    protected static string $resource = AmeliaAppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
