<?php

namespace App\Filament\Resources\AmeliaPackageResource\Pages;

use App\Filament\Resources\AmeliaPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaPackages extends ListRecords
{
    protected static string $resource = AmeliaPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

