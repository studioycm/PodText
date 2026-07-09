<?php

namespace App\Support\PublicFront\Colors;

class PublicFrontColor
{
    public const LIGHT_THEME_BACKGROUND = '#ffffff';

    public const DARK_THEME_BACKGROUND = '#030712';

    /**
     * @return array{light: string, dark: string}
     */
    public static function themeVariants(string $hex): array
    {
        $hex = self::normalizeHex($hex) ?? '#2563eb';

        return [
            'light' => self::themeSafeHex($hex, self::LIGHT_THEME_BACKGROUND, maxLightness: 0.4),
            'dark' => self::themeSafeHex($hex, self::DARK_THEME_BACKGROUND, minLightness: 0.65),
        ];
    }

    public static function normalizeHex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value, $matches)) {
            return null;
        }

        $hex = $matches[1];

        if (strlen($hex) === 3) {
            $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
        }

        return "#{$hex}";
    }

    /**
     * @param  array{light?: string, dark?: string}|string|null  $color
     */
    public static function cssVariables(string $prefix, array|string|null $color): ?string
    {
        if (is_string($color)) {
            $light = self::normalizeHex($color);
            $dark = $light;
        } else {
            $light = self::normalizeHex($color['light'] ?? null);
            $dark = self::normalizeHex($color['dark'] ?? null);
        }

        if ($light === null) {
            return null;
        }

        $dark ??= $light;

        return implode(' ', [
            "--{$prefix}-color: {$light};",
            "--{$prefix}-color-dark: {$dark};",
            "--{$prefix}-bg: color-mix(in srgb, {$light} 12%, transparent);",
            "--{$prefix}-bg-dark: color-mix(in srgb, {$dark} 18%, transparent);",
            "--{$prefix}-border: color-mix(in srgb, {$light} 32%, transparent);",
            "--{$prefix}-border-dark: color-mix(in srgb, {$dark} 38%, transparent);",
        ]);
    }

    public static function contrastRatio(string $foreground, string $background): float
    {
        $foregroundRgb = self::rgbFromHex($foreground);
        $backgroundRgb = self::rgbFromHex($background);

        if ($foregroundRgb === null || $backgroundRgb === null) {
            return 1.0;
        }

        $foregroundLuminance = self::relativeLuminance($foregroundRgb[0], $foregroundRgb[1], $foregroundRgb[2]);
        $backgroundLuminance = self::relativeLuminance($backgroundRgb[0], $backgroundRgb[1], $backgroundRgb[2]);
        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function lightness(string $hex): ?float
    {
        $rgb = self::rgbFromHex($hex);

        if ($rgb === null) {
            return null;
        }

        return self::rgbToHsl($rgb[0], $rgb[1], $rgb[2])['l'];
    }

    private static function themeSafeHex(
        string $hex,
        string $background,
        ?float $maxLightness = null,
        ?float $minLightness = null,
    ): string {
        $rgb = self::rgbFromHex($hex);

        if ($rgb === null) {
            return $maxLightness !== null ? '#111827' : '#f9fafb';
        }

        $hsl = self::rgbToHsl($rgb[0], $rgb[1], $rgb[2]);
        $targetLightness = $hsl['l'];

        if ($maxLightness !== null) {
            $targetLightness = min($targetLightness, $maxLightness);

            for ($lightness = $targetLightness; $lightness >= 0; $lightness -= 0.02) {
                $candidate = self::hexFromHsl($hsl['h'], $hsl['s'], max(0, $lightness));
                $candidateLightness = self::lightness($candidate);

                if ($candidateLightness !== null && $candidateLightness <= $maxLightness && self::contrastRatio($candidate, $background) >= 4.5) {
                    return $candidate;
                }
            }

            return '#111827';
        }

        $targetLightness = max($targetLightness, $minLightness ?? 0.65);

        for ($lightness = $targetLightness; $lightness <= 1; $lightness += 0.02) {
            $candidate = self::hexFromHsl($hsl['h'], $hsl['s'], min(1, $lightness));
            $candidateLightness = self::lightness($candidate);

            if ($candidateLightness !== null && $candidateLightness >= ($minLightness ?? 0.65) && self::contrastRatio($candidate, $background) >= 4.5) {
                return $candidate;
            }
        }

        return '#f9fafb';
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function rgbFromHex(string $hex): ?array
    {
        $hex = self::normalizeHex($hex);

        if ($hex === null) {
            return null;
        }

        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    private static function relativeLuminance(int $red, int $green, int $blue): float
    {
        $channels = array_map(
            fn (int $channel): float => ($channel / 255) <= 0.03928
                ? ($channel / 255) / 12.92
                : (($channel / 255 + 0.055) / 1.055) ** 2.4,
            [$red, $green, $blue],
        );

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    /**
     * @return array{h: float, s: float, l: float}
     */
    private static function rgbToHsl(int $red, int $green, int $blue): array
    {
        $red /= 255;
        $green /= 255;
        $blue /= 255;

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $lightness = ($max + $min) / 2;

        if ($max === $min) {
            return ['h' => 0.0, 's' => 0.0, 'l' => $lightness];
        }

        $delta = $max - $min;
        $saturation = $lightness > 0.5
            ? $delta / (2 - $max - $min)
            : $delta / ($max + $min);

        $hue = match ($max) {
            $red => (($green - $blue) / $delta) + ($green < $blue ? 6 : 0),
            $green => (($blue - $red) / $delta) + 2,
            default => (($red - $green) / $delta) + 4,
        };

        return [
            'h' => $hue * 60,
            's' => $saturation,
            'l' => $lightness,
        ];
    }

    private static function hexFromHsl(float $hue, float $saturation, float $lightness): string
    {
        $chroma = (1 - abs((2 * $lightness) - 1)) * $saturation;
        $huePrime = $hue / 60;
        $x = $chroma * (1 - abs(fmod($huePrime, 2) - 1));

        [$red, $green, $blue] = match (true) {
            $huePrime < 1 => [$chroma, $x, 0],
            $huePrime < 2 => [$x, $chroma, 0],
            $huePrime < 3 => [0, $chroma, $x],
            $huePrime < 4 => [0, $x, $chroma],
            $huePrime < 5 => [$x, 0, $chroma],
            default => [$chroma, 0, $x],
        };

        $match = $lightness - ($chroma / 2);

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($red + $match) * 255),
            (int) round(($green + $match) * 255),
            (int) round(($blue + $match) * 255),
        );
    }
}
