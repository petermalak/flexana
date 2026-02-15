<?php

namespace App\Filament\Resources\ClassTypeResource\Pages;

use App\Filament\Resources\ClassTypeResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageClassTypes extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = ClassTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

