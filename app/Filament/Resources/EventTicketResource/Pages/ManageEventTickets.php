<?php

namespace App\Filament\Resources\EventTicketResource\Pages;

use App\Filament\Resources\EventTicketResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageEventTickets extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = EventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
