<?php

namespace App\Filament\Resources\AmeliaCustomerResource\Pages;

use App\Filament\Resources\AmeliaCustomerResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAmeliaCustomers extends ListRecords
{
    protected static string $resource = AmeliaCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
