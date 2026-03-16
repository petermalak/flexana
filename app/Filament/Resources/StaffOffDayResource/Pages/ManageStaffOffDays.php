<?php

namespace App\Filament\Resources\StaffOffDayResource\Pages;

use App\Filament\Resources\StaffOffDayResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use App\Infrastructure\Persistence\Eloquent\StaffOffDayModel;
use Filament\Actions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

                    $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date'])->startOfDay() : null;
                    $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date'])->startOfDay() : null;
                    unset($data['start_date'], $data['end_date']);

                    if (! $startDate) {
                        $startDate = Carbon::today();
                    }
                    if (! $endDate || $endDate->lt($startDate)) {
                        $endDate = $startDate->copy();
                    }

                    $dates = [];
                    for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
                        $dates[] = $d->toDateString();
                    }

                    if ($applyToAll) {
                        $staffMembers = StaffModel::query()->get(['id']);

                        $firstRecord = null;

                        foreach ($staffMembers as $staff) {
                            foreach ($dates as $date) {
                                $payload = $data;
                                $payload['staff_id'] = $staff->id;
                                $payload['date'] = $date;

                                $record = StaffOffDayModel::query()->updateOrCreate(
                                    [
                                        'staff_id' => $payload['staff_id'],
                                        'date' => $payload['date'],
                                    ],
                                    $payload
                                );
                                static::cancelStaffAppointmentsForOffDay($record);

                                if ($firstRecord === null) {
                                    $firstRecord = $record;
                                }
                            }
                        }

                        return $firstRecord ?? new StaffOffDayModel();
                    }

                    $firstRecord = null;

                    foreach ($dates as $date) {
                        $payload = $data;
                        $payload['date'] = $date;

                        $record = StaffOffDayModel::query()->updateOrCreate(
                            [
                                'staff_id' => $payload['staff_id'],
                                'date' => $payload['date'],
                            ],
                            $payload
                        );
                        static::cancelStaffAppointmentsForOffDay($record);

                        if ($firstRecord === null) {
                            $firstRecord = $record;
                        }
                    }

                    return $firstRecord ?? new StaffOffDayModel();
                }),
        ];
    }

    /**
     * Cancel (hide) appointments for a staff member on their off day.
     * We set status to 'cancelled' so they are no longer returned to clients.
     */
    protected static function cancelStaffAppointmentsForOffDay(StaffOffDayModel $offDay): void
    {
        if (! $offDay->staff_id || ! $offDay->date) {
            return;
        }

        $date = Carbon::parse($offDay->date)->toDateString();

        $query = AppointmentModel::query()
            ->where('provider_id', $offDay->staff_id)
            ->whereDate('booking_start', $date)
            ->where('status', 'approved');

        if (! $offDay->is_all_day && $offDay->start_time && $offDay->end_time) {
            $start = Carbon::parse($date . ' ' . $offDay->start_time->format('H:i:s'));
            $end = Carbon::parse($date . ' ' . $offDay->end_time->format('H:i:s'));

            $query->where(function ($q) use ($start, $end) {
                $q->where('booking_start', '<', $end)
                    ->where('booking_end', '>', $start);
            });
        }

        $query->update(['status' => 'cancelled']);
    }
}
