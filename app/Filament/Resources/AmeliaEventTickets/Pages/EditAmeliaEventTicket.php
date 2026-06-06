<?php

namespace App\Filament\Resources\AmeliaEventTickets\Pages;

use App\Filament\Resources\AmeliaEventTickets\AmeliaEventTicketResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaEventTicket extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaEventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
