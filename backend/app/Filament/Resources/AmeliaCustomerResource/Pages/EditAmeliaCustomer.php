<?php

namespace App\Filament\Resources\AmeliaCustomerResource\Pages;

use App\Filament\Resources\AmeliaCustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmeliaCustomer extends EditRecord
{
    protected static string $resource = AmeliaCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

