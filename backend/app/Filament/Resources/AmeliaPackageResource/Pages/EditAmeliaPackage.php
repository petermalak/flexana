<?php

namespace App\Filament\Resources\AmeliaPackageResource\Pages;

use App\Filament\Resources\AmeliaPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaPackage extends EditRecord
{
    protected static string $resource = AmeliaPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

