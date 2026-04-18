<?php

namespace App\Filament\Resources\SmsMessageLogResource\Pages;

use App\Filament\Resources\Pages\ManageRecordsWithFullWidthForm;
use App\Filament\Resources\SmsMessageLogResource;

class ListSmsMessageLogs extends ManageRecordsWithFullWidthForm
{
    protected static string $resource = SmsMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

