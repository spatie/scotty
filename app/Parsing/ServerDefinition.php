<?php

namespace App\Parsing;

class ServerDefinition
{
    public function __construct(
        public string $name,
        public string $host,
    ) {}

    protected const LOCAL_HOSTS = ['127.0.0.1', 'localhost', 'local'];

    public function isLocal(): bool
    {
        return in_array($this->host, self::LOCAL_HOSTS);
    }

    public static function isLocalHost(string $host): bool
    {
        return in_array($host, self::LOCAL_HOSTS);
    }
}
