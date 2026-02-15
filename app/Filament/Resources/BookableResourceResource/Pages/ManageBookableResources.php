<?php

namespace App\Filament\Resources\BookableResourceResource\Pages;

use App\Filament\Resources\BookableResourceResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageBookableResources extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = BookableResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
