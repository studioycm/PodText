<?php

namespace App\Support\Transcriptions;

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use Illuminate\Support\Arr;
use Throwable;

class MultiTranscriptionSurfaces
{
    /**
     * @return array<int, array{settings: class-string, path: string, minimum: UserRole, requires_mode: bool}>
     */
    public static function settingsPaths(): array
    {
        return [
            [
                'settings' => PublicContentSettings::class,
                'path' => 'transcription_policy.public_mode',
                'minimum' => UserRole::SuperAdmin,
                'requires_mode' => true,
            ],
            [
                'settings' => PublicContentSettings::class,
                'path' => 'transcription_policy.count_mode',
                'minimum' => UserRole::SuperAdmin,
                'requires_mode' => true,
            ],
            [
                'settings' => PublicContentSettings::class,
                'path' => 'transcription_policy.show_multiple_transcriptions_on_item_page',
                'minimum' => UserRole::SuperAdmin,
                'requires_mode' => true,
            ],
            [
                'settings' => AdminUxSettings::class,
                'path' => 'transcription_mode',
                'minimum' => UserRole::SuperAdmin,
                'requires_mode' => false,
            ],
        ];
    }

    /**
     * @return array<int, array{source: string, attribute: string, minimum: UserRole, requires_mode: bool}>
     */
    public static function cardTemplateAttributes(): array
    {
        return [
            [
                'source' => 'content_item',
                'attribute' => 'transcription_count',
                'minimum' => UserRole::SuperAdmin,
                'requires_mode' => true,
            ],
        ];
    }

    public static function isMultiMode(): bool
    {
        try {
            return app(AdminUxSettings::class)->transcription_mode === TranscriptionMode::Multi->value;
        } catch (Throwable) {
            return false;
        }
    }

    public static function userCan(User $user, UserRole $minimum = UserRole::SuperAdmin, bool $requiresMode = true): bool
    {
        if (! $user->hasRoleAtLeast($minimum)) {
            return false;
        }

        if (! $requiresMode) {
            return true;
        }

        return self::isMultiMode();
    }

    public static function currentUserCan(UserRole $minimum = UserRole::SuperAdmin, bool $requiresMode = true): bool
    {
        $user = auth()->user();

        return $user instanceof User && self::userCan($user, $minimum, $requiresMode);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function overlayUnauthorizedSettings(
        array $data,
        string $settingsClass,
        ?User $user = null,
        ?array $storedSnapshot = null,
    ): array {
        $user ??= auth()->user();
        $stored = $storedSnapshot ?? app($settingsClass)->toArray();

        foreach (self::settingsPathsFor($settingsClass) as $surface) {
            if ($user instanceof User && self::userCan($user, $surface['minimum'], $surface['requires_mode'])) {
                continue;
            }

            data_set($data, $surface['path'], data_get($stored, $surface['path']));
        }

        if ($settingsClass === PublicContentSettings::class && array_key_exists('card_templates', $data)) {
            $data['card_templates'] = self::overlayUnauthorizedCardTemplates(
                incoming: is_array($data['card_templates']) ? $data['card_templates'] : [],
                stored: is_array($stored['card_templates'] ?? null) ? $stored['card_templates'] : [],
                user: $user instanceof User ? $user : null,
            );
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    public static function filterCardAttributeOptions(?string $source, array $options, ?string $currentAttribute = null): array
    {
        foreach (self::cardTemplateAttributes() as $surface) {
            if ($source !== $surface['source']) {
                continue;
            }

            if (self::currentUserCan($surface['minimum'], $surface['requires_mode'])) {
                continue;
            }

            if ($currentAttribute === $surface['attribute']) {
                continue;
            }

            unset($options[$surface['attribute']]);
        }

        return $options;
    }

    /**
     * @return array<int, array{settings: class-string, path: string, minimum: UserRole, requires_mode: bool}>
     */
    private static function settingsPathsFor(string $settingsClass): array
    {
        return collect(self::settingsPaths())
            ->filter(fn (array $surface): bool => $surface['settings'] === $settingsClass)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $incoming
     * @param  array<int, array<string, mixed>>  $stored
     * @return array<int, array<string, mixed>>
     */
    private static function overlayUnauthorizedCardTemplates(array $incoming, array $stored, ?User $user): array
    {
        $storedByIdentity = collect($stored)
            ->mapWithKeys(function (array $template): array {
                $identity = self::cardTemplateIdentity($template);

                return $identity ? [$identity => $template] : [];
            });

        $incomingIdentities = [];
        $guarded = [];

        foreach ($incoming as $template) {
            $identity = self::cardTemplateIdentity($template);

            if ($identity) {
                $incomingIdentities[$identity] = true;
            }

            $storedTemplate = $identity ? $storedByIdentity->get($identity) : null;

            if (
                is_array($storedTemplate)
                && self::containsUnauthorizedCardPart($storedTemplate['parts'] ?? [], $user)
            ) {
                $template['parts'] = $storedTemplate['parts'] ?? [];
                $guarded[] = $template;

                continue;
            }

            $template['parts'] = self::stripUnauthorizedCardParts($template['parts'] ?? [], $user);
            $guarded[] = $template;
        }

        foreach ($storedByIdentity as $identity => $storedTemplate) {
            if (isset($incomingIdentities[$identity])) {
                continue;
            }

            if (! self::containsUnauthorizedCardPart($storedTemplate['parts'] ?? [], $user)) {
                continue;
            }

            $guarded[] = $storedTemplate;
        }

        return array_values($guarded);
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $parts
     * @return array<int, array<string, mixed>>
     */
    private static function stripUnauthorizedCardParts(mixed $parts, ?User $user): array
    {
        if (! is_array($parts)) {
            return [];
        }

        return collect($parts)
            ->filter(fn (mixed $part): bool => is_array($part))
            ->map(function (array $part) use ($user): ?array {
                if (self::cardPartIsUnauthorized($part, $user)) {
                    return null;
                }

                if (self::cardPartHasChildren($part)) {
                    $part = self::replaceCardPartChildren(
                        $part,
                        self::stripUnauthorizedCardParts(self::cardPartChildren($part), $user),
                    );
                }

                return $part;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $parts
     */
    private static function containsUnauthorizedCardPart(mixed $parts, ?User $user): bool
    {
        if (! is_array($parts)) {
            return false;
        }

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            if (self::cardPartIsUnauthorized($part, $user)) {
                return true;
            }

            if (self::containsUnauthorizedCardPart(self::cardPartChildren($part), $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $part
     */
    private static function cardPartIsUnauthorized(array $part, ?User $user): bool
    {
        $part = self::unwrapBuilderPart($part);

        foreach (self::cardTemplateAttributes() as $surface) {
            if (($part['source'] ?? null) !== $surface['source']) {
                continue;
            }

            if (($part['attribute'] ?? null) !== $surface['attribute']) {
                continue;
            }

            return ! ($user instanceof User && self::userCan($user, $surface['minimum'], $surface['requires_mode']));
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    private static function unwrapBuilderPart(array $part): array
    {
        if (! is_array($part['data'] ?? null)) {
            return $part;
        }

        return [
            'type' => $part['type'] ?? null,
            ...$part['data'],
        ];
    }

    /**
     * @param  array<string, mixed>  $part
     */
    private static function cardPartHasChildren(array $part): bool
    {
        return array_key_exists('children', $part)
            || (
                is_array($part['data'] ?? null)
                && array_key_exists('children', $part['data'])
            );
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array<int, array<string, mixed>>
     */
    private static function cardPartChildren(array $part): array
    {
        $children = is_array($part['data'] ?? null) && array_key_exists('children', $part['data'])
            ? $part['data']['children']
            : ($part['children'] ?? []);

        return is_array($children) ? $children : [];
    }

    /**
     * @param  array<string, mixed>  $part
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private static function replaceCardPartChildren(array $part, array $children): array
    {
        if (is_array($part['data'] ?? null) && array_key_exists('children', $part['data'])) {
            $part['data']['children'] = $children;

            return $part;
        }

        $part['children'] = $children;

        return $part;
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private static function cardTemplateIdentity(array $template): ?string
    {
        $family = Arr::get($template, 'family');
        $key = Arr::get($template, 'key');

        if (! is_string($family) || blank($family) || ! is_string($key) || blank($key)) {
            return null;
        }

        return "{$family}:{$key}";
    }
}
