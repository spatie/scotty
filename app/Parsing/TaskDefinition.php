<?php

namespace App\Parsing;

class TaskDefinition
{
    public function __construct(
        public string $name,
        public string $script,
        /** @var array<string> */
        public array $servers = [],
        public bool $parallel = false,
        public ?string $confirm = null,
    ) {}
}
