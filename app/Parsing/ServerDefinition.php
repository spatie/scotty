<?php

namespace App\Parsing;

class ServerDefinition
{
    /** @var array<string> */
    public readonly array $hosts;

    public function __construct(
        public string $name,
        string|array $host,
    ) {
        $this->hosts = is_array($host) ? array_values($host) : [$host];
    }

    protected const LOCAL_HOSTS = ['127.0.0.1', 'localhost', 'local'];

    public function isLocal(): bool
    {
        return count($this->hosts) === 1 && in_array($this->hosts[0], self::LOCAL_HOSTS);
    }

    public static function isLocalHost(string $host): bool
    {
        return in_array($host, self::LOCAL_HOSTS);
    }
}
