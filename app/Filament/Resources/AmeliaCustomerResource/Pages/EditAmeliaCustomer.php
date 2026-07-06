<?php

namespace App\Filament\Resources\AmeliaCustomerResource\Pages;

use App\Filament\Resources\AmeliaCustomerResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditAmeliaCustomer extends StaysOnPageEditRecord
{
    protected static string $resource = AmeliaCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

