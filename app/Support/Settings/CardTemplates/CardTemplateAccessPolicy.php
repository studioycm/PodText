<?php

namespace App\Support\Settings\CardTemplates;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;

class CardTemplateAccessPolicy
{
    public function currentActorCanAccessEditor(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRoleAtLeast(UserRole::Admin);
    }

    public function currentActorCanManageProtectedTemplates(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && MultiTranscriptionSurfaces::userCan($user, UserRole::SuperAdmin, requiresMode: true);
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public function isProtected(array $template): bool
    {
        return $this->partsContainProtectedSurface($template['parts'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function readSafeTemplate(array $template, bool $capable): array
    {
        if ($capable || ! $this->isProtected($template)) {
            return $template;
        }

        unset($template['parts']);

        return $template;
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function stripProtectedParts(array $template): array
    {
        if (! is_array($template['parts'] ?? null)) {
            unset($template['parts']);

            return $template;
        }

        $template['parts'] = $this->stripProtectedFromList($template['parts']);

        return $template;
    }

    private function partsContainProtectedSurface(mixed $parts): bool
    {
        if (! is_array($parts)) {
            return true;
        }

        foreach ($parts as $part) {
            if (! is_array($part)) {
                return true;
            }

            $data = is_array($part['data'] ?? null) ? $part['data'] : $part;
            $source = $data['source'] ?? null;
            $attribute = $data['attribute'] ?? null;

            foreach (MultiTranscriptionSurfaces::cardTemplateAttributes() as $surface) {
                if ($source === $surface['source'] && $attribute === $surface['attribute']) {
                    return true;
                }
            }

            $children = $data['children'] ?? null;

            if ($children !== null && $this->partsContainProtectedSurface($children)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $parts
     * @return array<int, mixed>
     */
    private function stripProtectedFromList(array $parts): array
    {
        $safe = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $data = is_array($part['data'] ?? null) ? $part['data'] : $part;
            $protected = false;

            foreach (MultiTranscriptionSurfaces::cardTemplateAttributes() as $surface) {
                if (($data['source'] ?? null) === $surface['source']
                    && ($data['attribute'] ?? null) === $surface['attribute']) {
                    $protected = true;
                    break;
                }
            }

            if ($protected) {
                continue;
            }

            if (is_array($data['children'] ?? null)) {
                $children = $this->stripProtectedFromList($data['children']);

                if (is_array($part['data'] ?? null)) {
                    $part['data']['children'] = $children;
                } else {
                    $part['children'] = $children;
                }
            }

            $safe[] = $part;
        }

        return $safe;
    }
}
