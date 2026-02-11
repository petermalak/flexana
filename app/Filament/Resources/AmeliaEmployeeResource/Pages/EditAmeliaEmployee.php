<?php

namespace App\Filament\Resources\AmeliaEmployeeResource\Pages;

use App\Filament\Resources\AmeliaEmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaEmployee extends EditRecord
{
    protected static string $resource = AmeliaEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

