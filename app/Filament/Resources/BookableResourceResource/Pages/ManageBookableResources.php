<?php

namespace App\Filament\Resources\BookableResourceResource\Pages;

use App\Filament\Resources\BookableResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBookableResources extends ManageRecords
{
    protected static string $resource = BookableResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
