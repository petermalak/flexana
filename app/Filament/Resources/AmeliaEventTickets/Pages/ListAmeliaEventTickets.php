<?php

namespace App\Filament\Resources\AmeliaEventTickets\Pages;

use App\Filament\Resources\AmeliaEventTickets\AmeliaEventTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmeliaEventTickets extends ListRecords
{
    protected static string $resource = AmeliaEventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
