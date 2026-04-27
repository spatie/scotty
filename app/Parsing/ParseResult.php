<?php

namespace App\Parsing;

use RuntimeException;

final readonly class ParseResult
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
        return $this->resolveTasksForTargetRecursive($name, []);
    }

    /**
     * @param  array<string, true>  $visited  macro names already on the resolution stack
     * @return array<TaskDefinition>
     */
    protected function resolveTasksForTargetRecursive(string $name, array $visited): array
    {
        $macro = $this->getMacro($name);

        if ($macro === null) {
            $task = $this->getTask($name);

            if ($task === null) {
                return [];
            }

            return [$task];
        }

        if (isset($visited[$name])) {
            $cycle = implode(' -> ', [...array_keys($visited), $name]);

            throw new RuntimeException("Macro \"{$name}\" forms a cycle: {$cycle}");
        }

        $visited[$name] = true;

        $tasks = [];

        foreach ($macro->tasks as $childName) {
            if (! isset($this->macros[$childName]) && ! isset($this->tasks[$childName])) {
                throw new RuntimeException(
                    "Macro \"{$name}\" references unknown target \"{$childName}\"."
                );
            }

            $tasks[] = $this->resolveTasksForTargetRecursive($childName, $visited);
        }

        return array_merge([], ...$tasks);
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
