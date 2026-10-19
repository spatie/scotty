<?php

namespace App\Parsing;

use Closure;

final readonly class HookDefinition
{
    public function __construct(
        public HookType $type,
        public ?string $script = null,
        public ?Closure $callback = null,
    ) {}

    public function isCallback(): bool
    {
        return $this->callback !== null;
    }
}
