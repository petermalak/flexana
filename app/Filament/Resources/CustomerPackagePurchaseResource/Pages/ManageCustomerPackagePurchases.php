<?php

namespace App\Filament\Resources\CustomerPackagePurchaseResource\Pages;

use App\Filament\Resources\CustomerPackagePurchaseResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use Filament\Actions;

class ManageCustomerPackagePurchases extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = CustomerPackagePurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
