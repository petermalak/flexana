<?php

namespace App\Filament\Resources\AmeliaAppointments\Pages;

use App\Filament\Resources\AmeliaAppointments\AmeliaAppointmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaAppointment extends EditRecord
{
    protected static string $resource = AmeliaAppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
