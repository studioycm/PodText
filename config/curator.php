<?php

declare(strict_types=1);
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Filament\Resources\Media\Tables\MediaTable;
use App\Models\Media;
use App\Support\Media\CuratorPathGenerator;
use Awcodes\Curator\Providers\GlideUrlProvider;

return [
    'curation_formats' => ['jpg', 'png', 'webp'],
    'default_disk' => 'public',
    'default_directory' => null,
    'default_visibility' => 'public',
    'features' => [
        'curations' => false,
        'file_swap' => false,
        'directory_restriction' => true,
        'preserve_file_names' => false,
        'tenancy' => [
            'enabled' => false,
            'relationship_name' => null,
        ],
    ],
    'glide_token' => env('CURATOR_GLIDE_TOKEN', env('APP_KEY')),
    'model' => Media::class,
    'path_generator' => CuratorPathGenerator::class,
    'resource' => [
        'label' => 'Media',
        'plural_label' => 'Media',
        'default_layout' => 'grid',
        'navigation' => [
            'group' => null,
            'icon' => 'heroicon-o-photo',
            'sort' => null,
            'should_register' => true,
            'should_show_badge' => false,
        ],
        'resource' => MediaResource::class,
        'pages' => [
            'create' => CreateMedia::class,
            'edit' => EditMedia::class,
            'index' => ListMedia::class,
        ],
        'schemas' => [
            'form' => MediaForm::class,
        ],
        'tables' => [
            'table' => MediaTable::class,
        ],
    ],
    'url_provider' => GlideUrlProvider::class,
];
