<?php

namespace App\Filament\Resources\AmeliaEventPeriods\Pages;

use App\Filament\Resources\AmeliaEventPeriods\AmeliaEventPeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaEventPeriod extends EditRecord
{
    protected static string $resource = AmeliaEventPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
