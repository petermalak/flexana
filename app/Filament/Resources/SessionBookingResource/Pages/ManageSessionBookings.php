<?php

namespace App\Filament\Resources\SessionBookingResource\Pages;

use App\Application\Admin\SessionBooking\AdminSessionBookingService;
use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Filament\Resources\SessionBookingResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ManageSessionBookings extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = SessionBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New session booking')
                ->using(function (array $data): Model {
                    try {
                        return app(AdminSessionBookingService::class)->create($data);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Could not create booking')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        // Convert to a validation error so Livewire doesn't show a 500 error page.
                        throw ValidationException::withMessages([
                            'appointment_id' => $e->getMessage(),
                        ]);
                    }
                }),
        ];
    }
}

