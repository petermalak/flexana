<?php

namespace App\Filament\Resources\CompanyOffDayResource\Pages;

use App\Filament\Resources\CompanyOffDayResource;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\CompanyOffDayModel;
use Filament\Actions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ManageCompanyOffDays extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = CompanyOffDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data): Model {
                    /** @var CompanyOffDayModel $record */
                    $record = CompanyOffDayModel::query()->create($data);

                    static::cancelAppointmentsForCompanyHoliday($record);

                    return $record;
                }),
        ];
    }

    /**
     * Cancel (hide) all appointments that fall on a company holiday date.
     * We set status to 'cancelled' so they are no longer returned to clients.
     */
    protected static function cancelAppointmentsForCompanyHoliday(CompanyOffDayModel $holiday): void
    {
        if (! $holiday->date) {
            return;
        }

        $date = Carbon::parse($holiday->date)->toDateString();

        $query = AppointmentModel::query()
            ->whereDate('booking_start', $date)
            ->where('status', 'approved');

        $query->update(['status' => 'cancelled']);
    }
}
