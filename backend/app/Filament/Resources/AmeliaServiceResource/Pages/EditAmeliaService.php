<?php

namespace App\Filament\Resources\AmeliaServiceResource\Pages;

use App\Filament\Resources\AmeliaServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaService extends EditRecord
{
    protected static string $resource = AmeliaServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

