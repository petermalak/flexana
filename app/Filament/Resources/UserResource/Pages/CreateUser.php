<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\StaysOnPageCreateRecord;
use App\Filament\Resources\UserResource;
use App\Support\BranchContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUser extends StaysOnPageCreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeAdminAssignment($data);

        if (empty($data['firebase_uid'])) {
            $data['firebase_uid'] = Str::uuid()->toString();
        }

        unset($data['admin_role']);

        return $data;
    }

    protected function afterCreate(): void
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
