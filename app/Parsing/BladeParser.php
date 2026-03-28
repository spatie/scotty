<?php

namespace App\Parsing;

use App\Parsing\Blade\Compiler;
use App\Parsing\Blade\TaskContainer;

class BladeParser implements ParserInterface
{
    public function parse(string $filePath, array $data = []): ParseResult
    {
        $compiler = new Compiler;
        $container = new TaskContainer;

        $container->load($filePath, $compiler, $data);

        return new ParseResult(
            servers: $this->buildServers($container),
            tasks: $this->buildTasks($container),
            macros: $this->buildMacros($container),
            hooks: $this->buildHooks($container),
        );
    }

    /** @return array<string, ServerDefinition> */
    protected function buildServers(TaskContainer $container): array
    {
        $servers = [];

        foreach ($container->getServers() as $name => $host) {
            $servers[$name] = new ServerDefinition(name: $name, host: $host);
        }

        return $servers;
    }

    /** @return array<string, TaskDefinition> */
    protected function buildTasks(TaskContainer $container): array
    {
        $tasks = [];

        foreach ($container->getTasks() as $name => $script) {
            $options = $container->getTaskOptions($name);

            $serverNames = (array) ($options['on'] ?? []);

            $tasks[$name] = new TaskDefinition(
                name: $name,
                script: $script,
                servers: $serverNames,
                parallel: $options['parallel'] ?? false,
                confirm: $options['confirm'] ?? null,
            );
        }

        return $tasks;
    }

    /** @return array<string, MacroDefinition> */
    protected function buildMacros(TaskContainer $container): array
    {
        $macros = [];

        foreach ($container->getMacros() as $name => $taskNames) {
            $macros[$name] = new MacroDefinition(
                name: $name,
                tasks: array_filter($taskNames, fn (string $t) => $t !== ''),
            );
        }

        return $macros;
    }

    /** @return array<HookDefinition> */
    protected function buildHooks(TaskContainer $container): array
    {
        $hooks = [];

        $hookMapping = [
            [HookType::Before, $container->getBeforeCallbacks()],
            [HookType::After, $container->getAfterCallbacks()],
            [HookType::Success, $container->getSuccessCallbacks()],
            [HookType::Error, $container->getErrorCallbacks()],
            [HookType::Finished, $container->getFinishedCallbacks()],
        ];

        foreach ($hookMapping as [$type, $callbacks]) {
            foreach ($callbacks as $callback) {
                $hooks[] = new HookDefinition(
                    type: $type,
                    script: $this->extractCallbackBody($callback),
                );
            }
        }

        return $hooks;
    }

    /**
     * Hooks in Envoy are stored as closures. We capture them as-is since they
     * need to be invoked directly rather than converted to shell scripts.
     */
    protected function extractCallbackBody(\Closure $callback): string
    {
        $reflection = new \ReflectionFunction($callback);

        $file = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if ($file === false || $startLine === false) {
            return '';
        }

        $lines = file($file);
        $body = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return trim(implode('', $body));
    }
}
