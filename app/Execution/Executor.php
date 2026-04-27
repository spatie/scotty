<?php

namespace App\Execution;

use App\Parsing\HookType;
use App\Parsing\ParseResult;
use App\Parsing\TaskDefinition;
use Closure;
use Symfony\Component\Process\Process;

class Executor
{
    public function __construct(
        protected TaskRunner $taskRunner = new TaskRunner,
        protected PreambleEvaluator $preambleEvaluator = new PreambleEvaluator,
    ) {}

    /**
     * @param  array<string, string>  $env
     * @return array<string, TaskResult>
     */
    public function run(
        string $target,
        ParseResult $config,
        array $env = [],
        bool $continueOnError = false,
        bool $pretend = false,
        ?Closure $onTaskStart = null,
        ?Closure $onTaskOutput = null,
        ?Closure $onTaskComplete = null,
        ?Closure $onTick = null,
    ): array {
        $tasks = $config->resolveTasksForTarget($target);

        if ($tasks === []) {
            return [];
        }

        $tasks = $this->prependVariables($tasks, $config, $env);

        $results = [];

        foreach ($tasks as $task) {
            if ($onTaskStart !== null) {
                $onTaskStart($task, count($results), count($tasks));
            }

            if ($pretend) {
                $results[$task->name] = $this->pretendTask($task, $config, $env);

                if ($onTaskComplete !== null) {
                    $onTaskComplete($task, $results[$task->name]);
                }

                continue;
            }

            $this->runHooks($config, HookType::Before);

            $result = $this->taskRunner->run($task, $config, $env, $onTaskOutput, $onTick);
            $results[$task->name] = $result;

            $hookType = $result->succeeded() ? HookType::After : HookType::Error;
            $this->runHooks($config, $hookType);

            if ($onTaskComplete !== null) {
                $onTaskComplete($task, $result);
            }

            if (! $result->succeeded()) {
                if (! $continueOnError) {
                    break;
                }
            }
        }

        $totalExitCode = array_sum(array_map(fn (TaskResult $taskResult) => $taskResult->exitCode, $results));

        if ($totalExitCode === 0) {
            $this->runHooks($config, HookType::Success);
        }

        $this->runHooks($config, HookType::Finished);

        return $results;
    }

    /**
     * @param  array<TaskDefinition>  $tasks
     * @param  array<string, string>  $env
     * @return array<TaskDefinition>
     */
    protected function prependVariables(array $tasks, ParseResult $config, array $env): array
    {
        $evaluated = $this->preambleEvaluator->evaluate(
            $config->variablePreamble,
            $this->buildEnvForPreamble($env),
        );

        $assignments = $evaluated->variables;

        foreach ($env as $key => $value) {
            $upperKey = strtoupper(str_replace('-', '_', $key));

            if (isset($assignments[$upperKey])) {
                continue;
            }

            $assignments[$upperKey] = $value;
        }

        $assignmentLines = [];

        foreach ($assignments as $name => $value) {
            $assignmentLines[] = "{$name}=".escapeshellarg($value);
        }

        $preamble = implode("\n", $assignmentLines);

        if ($evaluated->remainingPreamble !== '') {
            $preamble = $preamble === ''
                ? $evaluated->remainingPreamble
                : "{$preamble}\n\n{$evaluated->remainingPreamble}";
        }

        $debugTrap = "trap 'echo \"ENVOY_TRACE:\$BASH_COMMAND\" >&2' DEBUG";

        $preamble = trim($preamble);
        $preamble = $preamble !== '' ? "{$preamble}\n{$debugTrap}" : $debugTrap;

        return array_map(fn (TaskDefinition $task) => new TaskDefinition(
            name: $task->name,
            script: "{$preamble}\n\n{$task->script}",
            servers: $task->servers,
            parallel: $task->parallel,
            confirm: $task->confirm,
            emoji: $task->emoji,
        ), $tasks);
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    protected function buildEnvForPreamble(array $env): array
    {
        $result = [];

        foreach ($env as $key => $value) {
            $upperKey = strtoupper(str_replace('-', '_', $key));
            $result[$upperKey] = $value;
        }

        return $result;
    }

    /** @param array<string, string> $env */
    protected function pretendTask(TaskDefinition $task, ParseResult $config, array $env): TaskResult
    {
        $commandBuilder = $this->taskRunner->getCommandBuilder();
        $output = '';

        foreach ($task->servers as $serverName) {
            $server = $config->getServer($serverName);

            if ($server === null) {
                continue;
            }

            foreach ($server->hosts as $host) {
                $command = $commandBuilder->buildCommand($host, $task->script, $env);
                $output .= "# On: {$serverName} ({$host})\n{$command}\n\n";
            }
        }

        return new TaskResult(
            exitCode: 0,
            outputs: ['pretend' => $output],
            duration: 0.0,
        );
    }

    protected function runHooks(ParseResult $config, HookType $type): void
    {
        foreach ($config->getHooks($type) as $hook) {
            $process = Process::fromShellCommandline($hook->script);
            $process->setTimeout(null);
            $process->run();
        }
    }
}
