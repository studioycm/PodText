<?php

namespace App\Support\Importer\SpotifyLinks;

enum SpotifyEntityMode: string
{
    case Episodes = 'episodes';
    case Shows = 'shows';

    public function entityType(): string
    {
        return match ($this) {
            self::Episodes => 'episode',
            self::Shows => 'show',
        };
    }

    public static function fromInput(string $value): self
    {
        return self::tryFrom($value) ?? self::Episodes;
    }
}
