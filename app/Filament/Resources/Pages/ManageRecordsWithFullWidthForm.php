<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Schema;

/**
 * Manage records page that uses a single-column form layout so create/edit modals
 * use full width (no empty half) on laptop and smaller screens.
 */
abstract class ManageRecordsWithFullWidthForm extends ManageRecords
{
    public function getDefaultActionSchemaResolver(Action $action): ?\Closure
    {
        return match (true) {
            $action instanceof CreateAction,
            $action instanceof EditAction => fn (Schema $schema): Schema => $this->form($schema->columns(1)),
            $action instanceof ViewAction => fn (Schema $schema): Schema => $this->infolist($this->form($schema->columns(1))),
            default => parent::getDefaultActionSchemaResolver($action),
        };
    }
}
