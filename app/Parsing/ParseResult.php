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
        /** @var array<string, OptionDefinition> */
        public array $options = [],
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

    /** @return array<TaskDefinition> */
    public function resolveTasksForTarget(string $name): array
    {
        $macro = $this->getMacro($name);

        if ($macro !== null) {
            return array_map(fn (string $taskName) => $this->tasks[$taskName], $macro->tasks);
        }

        $task = $this->getTask($name);

        if ($task !== null) {
            return [$task];
        }

        return [];
    }

    /** @return array<HookDefinition> */
    public function getHooks(HookType $type): array
    {
        return array_filter($this->hooks, fn (HookDefinition $hook) => $hook->type === $type);
    }

    /** @return array{tasks: array<string>, macros: array<string>} */
    public function availableTargets(): array
    {
        return [
            'tasks' => array_keys($this->tasks),
            'macros' => array_keys($this->macros),
        ];
    }
}
