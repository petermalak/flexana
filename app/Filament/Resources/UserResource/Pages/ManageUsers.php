<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Filament\Resources\UserResource;
use Filament\Actions;

class ManageUsers extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
