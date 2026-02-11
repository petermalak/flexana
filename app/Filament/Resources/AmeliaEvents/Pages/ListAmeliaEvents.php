<?php

namespace App\Filament\Resources\AmeliaEvents\Pages;

use App\Filament\Resources\AmeliaEvents\AmeliaEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaEvents extends ListRecords
{
    protected static string $resource = AmeliaEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
