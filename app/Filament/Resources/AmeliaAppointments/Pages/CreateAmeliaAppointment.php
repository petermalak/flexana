<?php

namespace App\Filament\Resources\AmeliaAppointments\Pages;

use App\Filament\Resources\AmeliaAppointments\AmeliaAppointmentResource;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\CompanyOffDayModel;
use App\Infrastructure\Persistence\Eloquent\StaffOffDayModel;
use Carbon\Carbon;
use App\Filament\Resources\Pages\StaysOnPageCreateRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateAmeliaAppointment extends StaysOnPageCreateRecord
{
    protected static string $resource = AmeliaAppointmentResource::class;

    /** @var array{day: string, count: int, group_id: string, duration_minutes: int, time_from: string}|null */
    protected ?array $pendingRecurring = null;

    private const DAY_MAP = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];

    public function mutateFormDataBeforeCreate(array $data): array
    {
        $createRecurring = ! empty($data['create_recurring']);
        $day = $data['create_recurring_day'] ?? null;
        $count = isset($data['create_recurring_count']) ? (int) $data['create_recurring_count'] : 0;

        if ($createRecurring && $day && $count >= 2) {
            $groupId = Str::uuid()->toString();
            $data['recurrence_group_id'] = $groupId;

            $bookingStart = Carbon::parse($data['booking_start']);
            $bookingEnd = Carbon::parse($data['booking_end']);
            $targetDayOfWeek = self::DAY_MAP[$day] ?? 1;

            // First occurrence: first date on or after booking_start that matches the selected day
            $firstDate = $bookingStart->copy()->startOfDay();
            while ($firstDate->dayOfWeek !== $targetDayOfWeek) {
                $firstDate->addDay();
            }
            $firstStart = $firstDate->copy()->setTime($bookingStart->hour, $bookingStart->minute, $bookingStart->second);
            $data['booking_start'] = $firstStart->format('Y-m-d H:i:s');
            $duration = $bookingStart->diffInMinutes($bookingEnd);
            $data['booking_end'] = $firstStart->copy()->addMinutes($duration)->format('Y-m-d H:i:s');

            $this->pendingRecurring = [
                'day' => $day,
                'count' => $count,
                'group_id' => $groupId,
                'duration_minutes' => $duration,
                'time_from' => $bookingStart->format('H:i:s'),
            ];

            $this->validateRecurringDates($data['provider_id'], $firstStart, $duration, $count);
        }

        unset($data['create_recurring'], $data['create_recurring_day'], $data['create_recurring_count']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingRecurring === null) {
            return;
        }

        $record = $this->record;
        if (! $record instanceof AppointmentModel) {
            return;
        }

        $count = $this->pendingRecurring['count'];
        $durationMinutes = $this->pendingRecurring['duration_minutes'];
        $groupId = $this->pendingRecurring['group_id'];

        $firstStart = Carbon::parse($record->booking_start);

        for ($i = 1; $i < $count; $i++) {
            $occurrenceStart = $firstStart->copy()->addWeeks($i);
            $occurrenceEnd = $occurrenceStart->copy()->addMinutes($durationMinutes);

            AppointmentModel::create([
                'recurrence_group_id' => $groupId,
                'service_id' => $record->service_id,
                'provider_id' => $record->provider_id,
                'package_id' => $record->package_id,
                'location_id' => $record->location_id,
                'booking_start' => $occurrenceStart->format('Y-m-d H:i:s'),
                'booking_end' => $occurrenceEnd->format('Y-m-d H:i:s'),
                'status' => $record->status,
                'internal_notes' => $record->internal_notes,
            ]);
        }

        $this->pendingRecurring = null;
    }

    private function validateRecurringDates(int $providerId, Carbon $firstStart, int $durationMinutes, int $count): void
    {
        $errors = [];
        for ($i = 0; $i < $count; $i++) {
            $occurrenceStart = $firstStart->copy()->addWeeks($i);
            $occurrenceEnd = $occurrenceStart->copy()->addMinutes($durationMinutes);
            $dateStr = $occurrenceStart->format('Y-m-d');

            $companyOff = CompanyOffDayModel::query()
                ->where('is_active', true)
                ->whereDate('date', $dateStr)
                ->first();
            if ($companyOff) {
                $errors['create_recurring'] = "One or more selected dates fall on a company off day ({$companyOff->name} on {$occurrenceStart->format('M j, Y')}). Please adjust the start date or number of occurrences.";
                break;
            }

            $staffOffDays = StaffOffDayModel::query()
                ->where('staff_id', $providerId)
                ->whereDate('date', $dateStr)
                ->get();
            foreach ($staffOffDays as $off) {
                if ($off->is_all_day) {
                    $errors['create_recurring'] = "The instructor has an off day on {$occurrenceStart->format('M j, Y')}. Please adjust the start date or number of occurrences.";
                    break 2;
                }
                if ($off->start_time && $off->end_time) {
                    $dayStr = $occurrenceStart->format('Y-m-d');
                    $offStart = Carbon::parse($dayStr . ' ' . Carbon::parse($off->start_time)->format('H:i:s'));
                    $offEnd = Carbon::parse($dayStr . ' ' . Carbon::parse($off->end_time)->format('H:i:s'));
                    if ($occurrenceStart->lt($offEnd) && $occurrenceEnd->gt($offStart)) {
                        $errors['create_recurring'] = "The instructor has an off period that overlaps with the appointment on {$occurrenceStart->format('M j, Y')}. Please adjust the start date or number of occurrences.";
                        break 2;
                    }
                }
            }

            $conflict = AppointmentModel::query()
                ->where('provider_id', $providerId)
                ->where('booking_start', '<', $occurrenceEnd->format('Y-m-d H:i:s'))
                ->where('booking_end', '>', $occurrenceStart->format('Y-m-d H:i:s'))
                ->first();
            if ($conflict) {
                $errors['create_recurring'] = "The instructor already has another appointment on {$occurrenceStart->format('M j, Y')} (" . $conflict->booking_start->format('H:i') . ' – ' . $conflict->booking_end->format('H:i') . "). Please adjust the start date or number of occurrences.";
                break;
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
