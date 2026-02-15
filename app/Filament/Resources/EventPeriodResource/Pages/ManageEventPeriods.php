<?php

namespace App\Filament\Resources\EventPeriodResource\Pages;

use App\Filament\Resources\EventPeriodResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageEventPeriods extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = EventPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
