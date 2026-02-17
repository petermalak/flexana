<?php

namespace App\Filament\Resources\StaffOffDayResource\Pages;

use App\Filament\Resources\StaffOffDayResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageStaffOffDays extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = StaffOffDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
