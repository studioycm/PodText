<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class AssignUserRole extends Command
{
    protected $signature = 'users:assign-role {email} {role}';

    protected $description = 'Assign one of the fixed PodText user roles to an existing user.';

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->argument('role'));

        if (! $role instanceof UserRole) {
            $this->components->error('Unknown role. Valid roles: '.implode(', ', UserRole::values()).'.');

            return self::FAILURE;
        }

        $user = User::query()
            ->where('email', $this->argument('email'))
            ->first();

        if (! $user instanceof User) {
            $this->components->error('No user was found for that email address.');

            return self::FAILURE;
        }

        $user->forceFill([
            'role' => $role,
        ])->save();

        $this->components->info("Assigned {$role->value} to {$user->email}.");

        return self::SUCCESS;
    }
}
