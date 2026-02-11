<?php

namespace App\Filament\Resources\AmeliaEmployeeResource\Pages;

use App\Filament\Resources\AmeliaEmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaEmployees extends ListRecords
{
    protected static string $resource = AmeliaEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

