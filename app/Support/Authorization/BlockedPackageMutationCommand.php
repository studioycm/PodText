<?php

namespace App\Support\Authorization;

use Illuminate\Console\Command;

final class BlockedPackageMutationCommand extends Command
{
    public function __construct(string $commandName)
    {
        $this->signature = $commandName;
        $this->description = 'Blocked in production because authorization package state is managed by an approved migration slice.';

        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->error('This authorization package mutation command is disabled in production.');

        return self::FAILURE;
    }
}
