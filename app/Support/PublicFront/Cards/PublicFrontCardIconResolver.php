<?php

namespace App\Support\PublicFront\Cards;

use Filament\Support\Icons\Heroicon;

class PublicFrontCardIconResolver
{
    public static function resolve(?string $key): ?Heroicon
    {
        return match ($key) {
            'image' => Heroicon::OutlinedPhoto,
            'title', 'description', 'document' => Heroicon::OutlinedDocumentText,
            'calendar' => Heroicon::OutlinedCalendar,
            'clock' => Heroicon::OutlinedClock,
            'tag' => Heroicon::OutlinedTag,
            'folder' => Heroicon::OutlinedFolder,
            'user' => Heroicon::OutlinedUser,
            'users' => Heroicon::OutlinedUsers,
            'microphone' => Heroicon::OutlinedMicrophone,
            'link' => Heroicon::OutlinedLink,
            'play' => Heroicon::OutlinedPlay,
            'podcast' => Heroicon::OutlinedRectangleGroup,
            'sparkles' => Heroicon::OutlinedSparkles,
            'arrow_right' => Heroicon::OutlinedArrowRight,
            default => null,
        };
    }
}
