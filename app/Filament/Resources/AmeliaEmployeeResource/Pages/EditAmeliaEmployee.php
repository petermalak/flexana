<?php

namespace App\Filament\Resources\AmeliaEmployeeResource\Pages;

use App\Filament\Resources\AmeliaEmployeeResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaEmployee extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

