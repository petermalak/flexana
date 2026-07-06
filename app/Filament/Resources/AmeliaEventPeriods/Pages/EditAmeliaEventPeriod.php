<?php

namespace App\Filament\Resources\AmeliaEventPeriods\Pages;

use App\Filament\Resources\AmeliaEventPeriods\AmeliaEventPeriodResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaEventPeriod extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaEventPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
