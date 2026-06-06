<?php

namespace App\Filament\Resources\AmeliaServiceResource\Pages;

use App\Filament\Resources\AmeliaServiceResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaService extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

