<?php

namespace App\Filament\Resources\AmeliaEventPeriods\Pages;

use App\Filament\Resources\AmeliaEventPeriods\AmeliaEventPeriodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaEventPeriods extends ListRecords
{
    protected static string $resource = AmeliaEventPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
