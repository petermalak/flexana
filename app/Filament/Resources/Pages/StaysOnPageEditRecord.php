<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\EditRecord as BaseEditRecord;

/**
 * After save, remain on the edit page instead of redirecting to the list.
 */
abstract class StaysOnPageEditRecord extends BaseEditRecord
{
    protected function getRedirectUrl(): ?string
    {
        $resource = static::getResource();

        if ($resource::hasPage('edit') && $resource::canEdit($this->getRecord())) {
            return $this->getResourceUrl('edit', ['record' => $this->getRecord()]);
        }

        return null;
    }
}
