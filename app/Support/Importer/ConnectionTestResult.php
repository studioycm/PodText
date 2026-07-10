<?php

namespace App\Support\Importer;

class ConnectionTestResult
{
    /**
     * @param  array<int, string>  $details
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $title,
        public readonly array $details = [],
    ) {}

    public function body(): string
    {
        if ($this->details === []) {
            return $this->title;
        }

        return collect($this->details)
            ->prepend($this->title)
            ->join(PHP_EOL);
    }
}
