<?php

namespace App\Parsing;

final readonly class MacroDefinition
{
    public function __construct(
        public string $name,
        /** @var array<string> */
        public array $tasks = [],
    ) {}
}
