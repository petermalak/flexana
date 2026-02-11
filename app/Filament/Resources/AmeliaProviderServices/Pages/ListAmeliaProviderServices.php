<?php

namespace App\Filament\Resources\AmeliaProviderServices\Pages;

use App\Filament\Resources\AmeliaProviderServices\AmeliaProviderServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaProviderServices extends ListRecords
{
    protected static string $resource = AmeliaProviderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
