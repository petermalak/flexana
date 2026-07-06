<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Models\Package;
use Filament\Actions;

class ManagePackages extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function (Package $record, array $data): void {
                    if (array_key_exists('branches', $data)) {
                        $record->branches()->sync($data['branches'] ?? []);
                    }
                }),
        ];
    }
}

