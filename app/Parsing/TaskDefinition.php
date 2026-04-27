<?php

namespace App\Parsing;

final readonly class TaskDefinition
{
    public function __construct(
        public string $name,
        public string $script,
        /** @var array<string> */
        public array $servers = [],
        public bool $parallel = false,
        public ?string $confirm = null,
        public ?string $emoji = null,
    ) {}

    public function displayName(): string
    {
        $humanized = preg_replace('/([a-z])([A-Z])/', '$1 $2', $this->name);
        $humanized = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $humanized);
        $humanized = str_replace(['-', '_'], ' ', $humanized);

        return ucfirst(mb_strtolower($humanized));
    }

    public function displayNameWithEmoji(): string
    {
        if ($this->emoji === null) {
            return $this->displayName();
        }

        return "{$this->emoji}  {$this->displayName()}";
    }
}
