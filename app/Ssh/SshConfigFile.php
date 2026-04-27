<?php

namespace App\Ssh;

class SshConfigFile
{
    /** @param array<int, array<string, string>> $groups */
    public function __construct(
        protected array $groups = [],
    ) {}

    public static function parse(string $file): self
    {
        return static::parseString(file_get_contents($file));
    }

    public static function parseString(string $string): self
    {
        $groups = [];
        $index = 0;
        $isMatchSection = false;

        foreach (explode(PHP_EOL, $string) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = self::parseKeyValue($line);

            if ($key === 'host') {
                $index++;
                $isMatchSection = false;
            } elseif ($key === 'match') {
                $isMatchSection = true;
            }

            if (! $isMatchSection) {
                $groups[$index][$key] = $value;
            }
        }

        return new self(array_values($groups));
    }

    public function findConfiguredHost(string $host): ?string
    {
        [$user, $hostname] = $this->parseHost($host);

        foreach ($this->groups as $group) {
            if (! $this->groupMatchesHostname($group, $hostname)) {
                continue;
            }

            if ($user !== null) {
                if (! isset($group['user']) || $group['user'] !== $user) {
                    continue;
                }
            }

            return preg_replace('/\s+.*$/', '', $group['host']);
        }

        return null;
    }

    /** @param array<string, string> $group */
    protected function groupMatchesHostname(array $group, string $hostname): bool
    {
        if (($group['host'] ?? null) === $hostname) {
            return true;
        }

        if (($group['hostname'] ?? null) === $hostname) {
            return true;
        }

        return false;
    }

    /** @return array{?string, string} */
    protected function parseHost(string $host): array
    {
        if (str_contains($host, '@')) {
            return explode('@', $host, 2);
        }

        return [null, $host];
    }

    /** @return array{string, string} */
    private static function parseKeyValue(string $line): array
    {
        if (preg_match('/^\s*(\S+)\s*=(.*)$/', $line, $match)) {
            return [strtolower($match[1]), self::unquote($match[2])];
        }

        $segments = preg_split('/\s+/', $line, 2);

        return [strtolower($segments[0]), self::unquote($segments[1] ?? '')];
    }

    private static function unquote(string $string): string
    {
        $string = trim($string);

        if (str_starts_with($string, '"')) {
            if (str_ends_with($string, '"')) {
                return substr($string, 1, -1);
            }
        }

        return $string;
    }
}
