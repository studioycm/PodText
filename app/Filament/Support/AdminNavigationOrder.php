<?php

namespace App\Filament\Support;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PublicContentSettings;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentTags\ContentTagResource;
use App\Filament\Resources\HomepageSections\HomepageSectionResource;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Filament\Resources\Transcriptions\TranscriptionResource;

class AdminNavigationOrder
{
    /**
     * @var array<class-string, int>
     */
    private const SORTS = [
        Dashboard::class => 0,
        ContentGroupResource::class => 10,
        ContentItemResource::class => 20,
        TranscriptionResource::class => 30,
        AuthorResource::class => 40,
        CategoryResource::class => 50,
        ContentTagResource::class => 60,
        PublicFormSubmissionResource::class => 70,
        HomepageSectionResource::class => 80,
        PublicContentSettings::class => 90,
        SettingsBackupResource::class => 95,
    ];

    public static function sort(string $class): ?int
    {
        return self::SORTS[$class] ?? null;
    }

    public static function has(string $class): bool
    {
        return array_key_exists($class, self::SORTS);
    }

    /**
     * @return array<class-string, int>
     */
    public static function all(): array
    {
        return self::SORTS;
    }
}
