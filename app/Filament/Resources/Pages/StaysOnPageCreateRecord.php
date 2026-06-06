<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

/**
 * After create, return to the resource list instead of opening the edit page.
 */
abstract class StaysOnPageCreateRecord extends BaseCreateRecord
{
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }
}
