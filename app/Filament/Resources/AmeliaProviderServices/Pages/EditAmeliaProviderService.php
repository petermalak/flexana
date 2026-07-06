<?php

namespace App\Filament\Resources\AmeliaProviderServices\Pages;

use App\Filament\Resources\AmeliaProviderServices\AmeliaProviderServiceResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaProviderService extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaProviderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
