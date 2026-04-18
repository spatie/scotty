<?php

namespace App\Parsing;

class OptionDefinition
{
    public function __construct(
        public string $name,
        public bool $isBoolean,
        public bool $isRequired,
        public ?string $default,
    ) {}
}
