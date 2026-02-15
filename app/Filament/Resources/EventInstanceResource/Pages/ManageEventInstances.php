<?php

namespace App\Filament\Resources\EventInstanceResource\Pages;

use App\Filament\Resources\EventInstanceResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageEventInstances extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = EventInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

