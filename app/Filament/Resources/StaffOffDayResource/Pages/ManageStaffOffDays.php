<?php

namespace App\Filament\Resources\StaffOffDayResource\Pages;

use App\Filament\Resources\StaffOffDayResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use App\Infrastructure\Persistence\Eloquent\StaffOffDayModel;
use Filament\Actions;
use Illuminate\Database\Eloquent\Model;

class ManageStaffOffDays extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = StaffOffDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data): Model {
                    $applyToAll = (bool) ($data['apply_to_all_staff'] ?? false);
                    unset($data['apply_to_all_staff']);

                    if ($applyToAll) {
                        $staffMembers = StaffModel::query()->get(['id']);

                        $firstRecord = null;

                        foreach ($staffMembers as $staff) {
                            $payload = $data;
                            $payload['staff_id'] = $staff->id;

                            $record = StaffOffDayModel::query()->create($payload);

                            if ($firstRecord === null) {
                                $firstRecord = $record;
                            }
                        }

                        return $firstRecord ?? new StaffOffDayModel();
                    }

                    return StaffOffDayModel::query()->create($data);
                }),
        ];
    }
}
