<?php

namespace App\Parsing;

class HookDefinition
{
    public function __construct(
        public HookType $type,
        public string $script,
    ) {}
}
