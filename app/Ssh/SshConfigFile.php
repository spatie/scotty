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
        $matchSection = false;

        foreach (explode(PHP_EOL, $string) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^\s*(\S+)\s*=(.*)$/', $line, $match)) {
                $key = strtolower($match[1]);
                $value = self::unquote($match[2]);
            } else {
                $segments = preg_split('/\s+/', $line, 2);
                $key = strtolower($segments[0]);
                $value = self::unquote($segments[1] ?? '');
            }

            if ($key === 'host') {
                $index++;
                $matchSection = false;
            } elseif ($key === 'match') {
                $matchSection = true;
            }

            if (! $matchSection) {
                $groups[$index][$key] = $value;
            }
        }

        return new self(array_values($groups));
    }

    public function findConfiguredHost(string $host): ?string
    {
        [$user, $host] = $this->parseHost($host);

        foreach ($this->groups as $group) {
            $hostMatches = (isset($group['host']) && $group['host'] === $host)
                || (isset($group['hostname']) && $group['hostname'] === $host);

            if (! $hostMatches) {
                continue;
            }

            if ($user !== null) {
                if (! isset($group['user'])) {
                    continue;
                }

                if ($group['user'] !== $user) {
                    continue;
                }
            }

            return preg_replace('/\s+.*$/', '', $group['host']);
        }

        return null;
    }

    /** @return array{?string, string} */
    protected function parseHost(string $host): array
    {
        if (str_contains($host, '@')) {
            return explode('@', $host, 2);
        }

        return [null, $host];
    }

    private static function unquote(string $string): string
    {
        $string = trim($string);

        if (str_starts_with($string, '"') && str_ends_with($string, '"')) {
            return substr($string, 1, -1);
        }

        return $string;
    }
}
