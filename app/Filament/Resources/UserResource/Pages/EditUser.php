<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\StaysOnPageEditRecord;
use App\Filament\Resources\UserResource;
use App\Support\BranchContext;
use Filament\Actions\DeleteAction;
use Illuminate\Validation\ValidationException;

class EditUser extends StaysOnPageEditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['admin_role'] = $this->record->roles()->value('name');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->normalizeAdminAssignment($data);

        unset($data['admin_role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $role = (string) ($this->form->getState()['admin_role'] ?? '');

        if ($role !== '') {
            $this->record->syncRoles([$role]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAdminAssignment(array $data): array
    {
        $role = (string) ($data['admin_role'] ?? '');

        if ($role === BranchContext::ROLE_SUPER_ADMIN) {
            $data['branch_id'] = null;

            return $data;
        }

        if ($role === BranchContext::ROLE_BRANCH_ADMIN && empty($data['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Select a branch for branch admin users.',
            ]);
        }

        return $data;
    }
}
