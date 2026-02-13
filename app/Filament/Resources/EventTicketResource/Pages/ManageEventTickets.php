<?php

namespace App\Filament\Resources\EventTicketResource\Pages;

use App\Filament\Resources\EventTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEventTickets extends ManageRecords
{
    protected static string $resource = EventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
