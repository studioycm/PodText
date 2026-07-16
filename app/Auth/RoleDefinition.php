<?php

namespace App\Auth;

final readonly class RoleDefinition
{
    public function __construct(
        public string $role,
        public bool $protected,
        public bool $reserved,
        public bool $delegable,
    ) {}

    /**
     * @return array{role: string, protected: bool, reserved: bool, delegable: bool}
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'protected' => $this->protected,
            'reserved' => $this->reserved,
            'delegable' => $this->delegable,
        ];
    }
}
