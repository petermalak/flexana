<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Actions;
use App\Filament\Resources\Pages\StaysOnPageEditRecord;

class EditStaff extends StaysOnPageEditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('schedule')
                ->label('Back to schedule')
                ->icon('heroicon-o-calendar-days')
                ->url(fn () => StaffResource::getUrl('schedule', ['record' => $this->getRecord()]))
                ->color('gray'),
            Actions\DeleteAction::make(),
        ];
    }
}
