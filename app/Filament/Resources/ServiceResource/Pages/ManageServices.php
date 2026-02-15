<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Filament\Resources\ServiceResource;
use Filament\Actions;

class ManageServices extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

