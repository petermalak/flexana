<?php

namespace App\Filament\Resources\AmeliaAppointments\Pages;

use App\Filament\Resources\AmeliaAppointments\AmeliaAppointmentResource;
use App\Support\BranchContext;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaAppointment extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaAppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($branchId = BranchContext::scopedBranchId()) {
            $data['branch_id'] = $branchId;
        }

        return $data;
    }
}
