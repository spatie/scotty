<?php

namespace App\Parsing;

class ServerDefinition
{
    public function __construct(
        public string $name,
        public string $host,
    ) {}

    public function isLocal(): bool
    {
        return in_array($this->host, ['127.0.0.1', 'localhost', 'local']);
    }
}
