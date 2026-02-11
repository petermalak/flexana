<?php

namespace App\Filament\Resources\AmeliaBookingResource\Pages;

use App\Filament\Resources\AmeliaBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaBooking extends EditRecord
{
    protected static string $resource = AmeliaBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

