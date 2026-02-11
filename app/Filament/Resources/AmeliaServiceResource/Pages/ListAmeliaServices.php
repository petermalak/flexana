<?php

namespace App\Filament\Resources\AmeliaServiceResource\Pages;

use App\Filament\Resources\AmeliaServiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAmeliaServices extends ListRecords
{
    protected static string $resource = AmeliaServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
