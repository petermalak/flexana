<?php

namespace App\Filament\Resources\AmeliaBookingResource\Pages;

use App\Filament\Resources\AmeliaBookingResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaBooking extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

