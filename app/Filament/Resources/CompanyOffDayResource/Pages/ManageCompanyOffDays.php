<?php

namespace App\Filament\Resources\CompanyOffDayResource\Pages;

use App\Filament\Resources\CompanyOffDayResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageCompanyOffDays extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = CompanyOffDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
