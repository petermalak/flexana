<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
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
