<?php

namespace App\Filament\Resources\AmeliaEvents\Pages;

use App\Filament\Resources\AmeliaEvents\AmeliaEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaEvent extends EditRecord
{
    protected static string $resource = AmeliaEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
