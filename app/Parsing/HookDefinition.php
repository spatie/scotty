<?php

namespace App\Parsing;

final readonly class HookDefinition
{
    public function __construct(
        public HookType $type,
        public string $script,
    ) {}
}
