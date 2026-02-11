<?php

namespace App\Filament\Resources\AmeliaEventTickets\Pages;

use App\Filament\Resources\AmeliaEventTickets\AmeliaEventTicketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaEventTicket extends EditRecord
{
    protected static string $resource = AmeliaEventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
