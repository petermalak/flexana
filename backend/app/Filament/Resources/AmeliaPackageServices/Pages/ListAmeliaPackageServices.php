<?php

namespace App\Filament\Resources\AmeliaPackageServices\Pages;

use App\Filament\Resources\AmeliaPackageServices\AmeliaPackageServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaPackageServices extends ListRecords
{
    protected static string $resource = AmeliaPackageServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
