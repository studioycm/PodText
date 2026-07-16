<?php

namespace App\Auth;

final readonly class AbilityDefinition
{
    public function __construct(
        public string $key,
        public string $group,
        public int $groupOrder,
        public int $entryOrder,
        public string $labelKey,
        public string $descriptionKey,
        public bool $sensitive,
        public bool $delegable,
        public ?string $protectedDomain,
        public string $guard,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     group: string,
     *     group_order: int,
     *     entry_order: int,
     *     label_key: string,
     *     description_key: string,
     *     sensitive: bool,
     *     delegable: bool,
     *     protected_domain: string|null,
     *     guard: string
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'group' => $this->group,
            'group_order' => $this->groupOrder,
            'entry_order' => $this->entryOrder,
            'label_key' => $this->labelKey,
            'description_key' => $this->descriptionKey,
            'sensitive' => $this->sensitive,
            'delegable' => $this->delegable,
            'protected_domain' => $this->protectedDomain,
            'guard' => $this->guard,
        ];
    }
}
