<?php

namespace App\Parsing;

class MacroDefinition
{
    public function __construct(
        public string $name,
        /** @var array<string> */
        public array $tasks = [],
    ) {}
}
