<?php

namespace App\Support;

final class LocaleDirection
{
    /**
     * @var array<string, true>
     */
    private const RTL_LOCALES = [
        'ar' => true,
        'ckb' => true,
        'fa' => true,
        'he' => true,
        'ku' => true,
        'ur' => true,
    ];

    public static function forLocale(?string $locale = null): string
    {
        $language = str($locale ?? app()->getLocale())
            ->before('_')
            ->before('-')
            ->lower()
            ->toString();

        return isset(self::RTL_LOCALES[$language]) ? 'rtl' : 'ltr';
    }
}
