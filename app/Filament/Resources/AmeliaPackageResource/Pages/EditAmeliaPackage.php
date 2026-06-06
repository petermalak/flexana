<?php

namespace App\Filament\Resources\AmeliaPackageResource\Pages;

use App\Filament\Resources\AmeliaPackageResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaPackage extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

