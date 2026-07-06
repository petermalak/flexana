<?php

namespace App\Filament\Resources\AmeliaEvents\Pages;

use App\Filament\Resources\AmeliaEvents\AmeliaEventResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaEvent extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
