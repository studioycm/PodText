<?php

namespace App\Support\Transcriptions;

use Illuminate\Support\Facades\Lang;

class TranscriptionModeLabel
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function text(string $baseKey, array $replace = [], ?string $singleKey = null): string
    {
        return __(self::key($baseKey, $singleKey), $replace);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function choice(
        string $baseKey,
        int $number,
        array $replace = [],
        ?string $singleKey = null,
    ): string {
        return trans_choice(self::key($baseKey, $singleKey), $number, $replace);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function singleText(string $baseKey, array $replace = [], ?string $singleKey = null): ?string
    {
        if (MultiTranscriptionSurfaces::isMultiMode()) {
            return null;
        }

        $singleKey ??= self::derivedSingleKey($baseKey);

        return Lang::has($singleKey) ? __($singleKey, $replace) : null;
    }

    private static function key(string $baseKey, ?string $singleKey): string
    {
        if (MultiTranscriptionSurfaces::isMultiMode()) {
            return $baseKey;
        }

        $singleKey ??= self::derivedSingleKey($baseKey);

        return Lang::has($singleKey) ? $singleKey : $baseKey;
    }

    private static function derivedSingleKey(string $baseKey): string
    {
        $separator = strrpos($baseKey, '.');

        if ($separator === false) {
            return "single.{$baseKey}";
        }

        return substr($baseKey, 0, $separator).'.single.'.substr($baseKey, $separator + 1);
    }
}
