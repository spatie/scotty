<?php

namespace App\Parsing;

class ParseResult
{
    public function __construct(
        /** @var array<string, ServerDefinition> */
        public array $servers = [],
        /** @var array<string, TaskDefinition> */
        public array $tasks = [],
        /** @var array<string, MacroDefinition> */
        public array $macros = [],
        /** @var array<HookDefinition> */
        public array $hooks = [],
        public string $variablePreamble = '',
    ) {}

    public function getTask(string $name): ?TaskDefinition
    {
        return $this->tasks[$name] ?? null;
    }

    public function getMacro(string $name): ?MacroDefinition
    {
        return $this->macros[$name] ?? null;
    }

    public function getServer(string $name): ?ServerDefinition
    {
        return $this->servers[$name] ?? null;
    }

    public function resolveTasksForTarget(string $name): array
    {
        if ($macro = $this->getMacro($name)) {
            return array_map(fn (string $taskName) => $this->tasks[$taskName], $macro->tasks);
        }

        if ($task = $this->getTask($name)) {
            return [$task];
        }

        return [];
    }

    public function getHooks(HookType $type): array
    {
        return array_filter($this->hooks, fn (HookDefinition $hook) => $hook->type === $type);
    }

    public function availableTargets(): array
    {
        return [
            'tasks' => array_keys($this->tasks),
            'macros' => array_keys($this->macros),
        ];
    }
}
