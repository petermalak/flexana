<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Filament\Resources\PromoCodeResource;
use Filament\Actions;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditPromoCode extends StaysOnPageEditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
