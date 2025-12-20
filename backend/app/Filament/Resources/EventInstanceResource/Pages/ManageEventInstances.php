<?php

namespace App\Filament\Resources\EventInstanceResource\Pages;

use App\Filament\Resources\EventInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEventInstances extends ManageRecords
{
    protected static string $resource = EventInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

