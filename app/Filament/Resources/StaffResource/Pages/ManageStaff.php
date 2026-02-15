<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageStaff extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

