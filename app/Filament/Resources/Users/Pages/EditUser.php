<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $newRole = UserRole::tryFrom((string) ($data['role'] ?? ''));
        $record = $this->getRecord();

        if (! $record instanceof User || ! $newRole instanceof UserRole) {
            return $data;
        }

        if (
            $record->role === UserRole::SuperAdmin
            && $newRole !== UserRole::SuperAdmin
            && User::query()->where('role', UserRole::SuperAdmin->value)->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'role' => __('admin.validation.cannot_demote_last_super_admin'),
            ]);
        }

        if (
            $record->is(auth()->user())
            &&
            $record->role === UserRole::SuperAdmin
            && $newRole !== UserRole::SuperAdmin
        ) {
            throw ValidationException::withMessages([
                'role' => __('admin.validation.cannot_demote_self'),
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
