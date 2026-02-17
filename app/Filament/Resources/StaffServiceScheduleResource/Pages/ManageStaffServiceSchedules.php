<?php

namespace App\Filament\Resources\StaffServiceScheduleResource\Pages;

use App\Filament\Resources\StaffServiceScheduleResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;
use Filament\Forms\Get;

class ManageStaffServiceSchedules extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = StaffServiceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Add record ID to form data for validation
        if ($this->record) {
            $data['id'] = $this->record->id;
        }
        return $data;
    }
}
