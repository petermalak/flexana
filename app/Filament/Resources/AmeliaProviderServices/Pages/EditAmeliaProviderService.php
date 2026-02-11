<?php

namespace App\Filament\Resources\AmeliaProviderServices\Pages;

use App\Filament\Resources\AmeliaProviderServices\AmeliaProviderServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaProviderService extends EditRecord
{
    protected static string $resource = AmeliaProviderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
