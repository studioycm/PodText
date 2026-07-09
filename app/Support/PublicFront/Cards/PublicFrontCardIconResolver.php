<?php

namespace App\Support\PublicFront\Cards;

use App\Support\PublicFront\Icons\PublicFrontIconRegistry;
use Filament\Support\Icons\Heroicon;

class PublicFrontCardIconResolver
{
    public static function resolve(?string $key): ?Heroicon
    {
        return PublicFrontIconRegistry::resolve($key);
    }
}
