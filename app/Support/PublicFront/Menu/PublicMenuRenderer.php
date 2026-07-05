<?php

namespace App\Support\PublicFront\Menu;

class PublicMenuRenderer
{
    public function __construct(
        private readonly PublicMenuConfigReader $reader,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     items: array<int, array<string, mixed>>,
     *     form_mounts: array<int, array{form_key: string, display_mode: string}>,
     *     theme_selector: array<string, mixed>,
     * }
     */
    public function config(): array
    {
        return $this->reader->read();
    }
}
