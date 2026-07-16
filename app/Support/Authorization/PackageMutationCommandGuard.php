<?php

namespace App\Support\Authorization;

use Illuminate\Console\Application as ArtisanApplication;

final class PackageMutationCommandGuard
{
    /**
     * @var list<string>
     */
    public const COMMANDS = [
        'shield:generate',
        'shield:install',
        'shield:publish',
        'shield:seeder',
        'shield:setup',
        'shield:super-admin',
        'shield:translation',
        'permission:assign-role',
        'permission:create-permission',
        'permission:create-role',
        'permission:setup-teams',
    ];

    public static function register(): void
    {
        ArtisanApplication::starting(function (ArtisanApplication $artisan): void {
            if (! app()->isProduction()) {
                return;
            }

            self::block($artisan);
        });
    }

    public static function block(ArtisanApplication $artisan): void
    {
        foreach (self::COMMANDS as $commandName) {
            $artisan->add(new BlockedPackageMutationCommand($commandName));
        }
    }
}
