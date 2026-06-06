<?php

namespace App\Filament\Resources\AmeliaPackageServices\Pages;

use App\Filament\Resources\AmeliaPackageServices\AmeliaPackageServiceResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaPackageService extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaPackageServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
