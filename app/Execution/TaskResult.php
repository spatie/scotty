<?php

namespace App\Execution;

class TaskResult
{
    /**
     * @param array<string, string> $outputs Output per host.
     */
    public function __construct(
        public int $exitCode,
        public array $outputs = [],
        public float $duration = 0.0,
        public ?string $failedHost = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
