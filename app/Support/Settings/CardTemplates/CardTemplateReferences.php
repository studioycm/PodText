<?php

namespace App\Support\Settings\CardTemplates;

class CardTemplateReferences
{
    /**
     * @param  array<string, array{settings: array<int, string>, sections: array<int, array{id: int, name: string}>, implicit: int}>  $references
     * @param  array<string, array<int, array{id: int, name: string}>>  $ambiguousKeys
     */
    public function __construct(
        public readonly array $references,
        public readonly array $ambiguousKeys,
        public readonly int $sectionRows,
        public readonly float $milliseconds,
    ) {}

    /**
     * @return array{settings: array<int, string>, sections: array<int, array{id: int, name: string}>, implicit: int}
     */
    public function for(string $identity): array
    {
        return $this->references[$identity] ?? [
            'settings' => [],
            'sections' => [],
            'implicit' => 0,
        ];
    }

    public function explicitCount(string $identity): int
    {
        $reference = $this->for($identity);

        return count($reference['settings']) + count($reference['sections']);
    }

    public function blocksMutation(string $family, string $key): bool
    {
        return $this->explicitCount("{$family}:{$key}") > 0
            || isset($this->ambiguousKeys[$key]);
    }

    /**
     * @return array<int, string>
     */
    public function blockerLabels(string $family, string $key): array
    {
        $reference = $this->for("{$family}:{$key}");
        $labels = $reference['settings'];

        foreach ($reference['sections'] as $section) {
            $labels[] = "#{$section['id']} {$section['name']}";
        }

        foreach ($this->ambiguousKeys[$key] ?? [] as $section) {
            $labels[] = "#{$section['id']} {$section['name']}";
        }

        return array_values(array_unique($labels));
    }
}
