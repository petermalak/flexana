<?php

namespace App\Filament\Resources\AmeliaBookingResource\Pages;

use App\Filament\Resources\AmeliaBookingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAmeliaBookings extends ListRecords
{
    protected static string $resource = AmeliaBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
