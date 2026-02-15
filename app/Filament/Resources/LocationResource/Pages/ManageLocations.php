<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageLocations extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
