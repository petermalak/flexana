<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageSettings extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
