<?php

namespace App\Execution;

use App\Parsing\ParseResult;
use App\Parsing\TaskDefinition;
use Closure;
use Symfony\Component\Process\Process;

class TaskRunner
{
    public function __construct(
        protected SshCommandBuilder $commandBuilder = new SshCommandBuilder,
    ) {}

    /**
     * @param array<string, string> $env
     */
    public function run(
        TaskDefinition $task,
        ParseResult $config,
        array $env = [],
        ?Closure $onOutput = null,
        ?Closure $onTick = null,
    ): TaskResult {
        $startTime = microtime(true);

        $serverMap = $this->resolveServerMap($task, $config);

        if ($serverMap === []) {
            return new TaskResult(
                exitCode: 0,
                duration: microtime(true) - $startTime,
            );
        }

        $processes = $this->buildProcesses($serverMap, $task->script, $env);

        if ($task->parallel) {
            $result = $this->runParallel($processes, $onOutput, $onTick);
        } else {
            $result = $this->runSequential($processes, $onOutput, $onTick);
        }

        $result->duration = microtime(true) - $startTime;

        return $result;
    }

    /**
     * Resolve server names to hosts, returning name => host mapping.
     *
     * @return array<string, string>
     */
    protected function resolveServerMap(TaskDefinition $task, ParseResult $config): array
    {
        $map = [];

        foreach ($task->servers as $serverName) {
            $server = $config->getServer($serverName);

            if ($server !== null) {
                $map[$serverName] = $server->host;
            }
        }

        return $map;
    }

    /**
     * @param array<string, string> $serverMap name => host
     * @param array<string, string> $env
     * @return array<string, Process> keyed by server name
     */
    protected function buildProcesses(array $serverMap, string $script, array $env): array
    {
        $processes = [];

        foreach ($serverMap as $name => $host) {
            $processes[$name] = $this->commandBuilder->buildProcess($host, $script, $env);
        }

        return $processes;
    }

    /**
     * Run processes sequentially using non-blocking polling (allows tick callbacks).
     *
     * @param array<string, Process> $processes keyed by server name
     */
    protected function runSequential(array $processes, ?Closure $onOutput, ?Closure $onTick): TaskResult
    {
        $outputs = [];
        $exitCode = 0;
        $failedHost = null;

        foreach ($processes as $name => $process) {
            $outputs[$name] = '';
            $process->start();

            while ($process->isRunning()) {
                $this->gatherOutput([$name => $process], $outputs, $onOutput);

                if ($onTick !== null) {
                    $onTick();
                }

                usleep(80_000);
            }

            // Final gather
            $this->gatherOutput([$name => $process], $outputs, $onOutput);

            $hostExitCode = $process->getExitCode() ?? 0;
            $exitCode += $hostExitCode;

            if ($hostExitCode !== 0 && $failedHost === null) {
                $failedHost = $name;
            }
        }

        return new TaskResult(
            exitCode: $exitCode,
            outputs: $outputs,
            failedHost: $failedHost,
        );
    }

    /**
     * Run processes in parallel across all hosts.
     *
     * @param array<string, Process> $processes keyed by server name
     */
    protected function runParallel(array $processes, ?Closure $onOutput, ?Closure $onTick): TaskResult
    {
        $outputs = [];
        $failedHost = null;

        foreach ($processes as $name => $process) {
            $outputs[$name] = '';
            $process->start();
        }

        while ($this->hasRunning($processes)) {
            $this->gatherOutput($processes, $outputs, $onOutput);

            if ($onTick !== null) {
                $onTick();
            }

            usleep(80_000);
        }

        $this->gatherOutput($processes, $outputs, $onOutput);

        $exitCode = 0;

        foreach ($processes as $name => $process) {
            $hostExitCode = $process->getExitCode() ?? 0;
            $exitCode += $hostExitCode;

            if ($hostExitCode !== 0 && $failedHost === null) {
                $failedHost = $name;
            }
        }

        return new TaskResult(
            exitCode: $exitCode,
            outputs: $outputs,
            failedHost: $failedHost,
        );
    }

    /** @param array<string, Process> $processes */
    protected function hasRunning(array $processes): bool
    {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, Process> $processes keyed by server name
     * @param array<string, string> $outputs
     */
    protected function gatherOutput(array $processes, array &$outputs, ?Closure $onOutput): void
    {
        foreach ($processes as $name => $process) {
            $stdout = $process->getIncrementalOutput();
            $stderr = $process->getIncrementalErrorOutput();

            if ($stdout !== '') {
                $outputs[$name] .= $stdout;

                if ($onOutput !== null) {
                    $onOutput(Process::OUT, $name, $stdout);
                }
            }

            if ($stderr !== '') {
                $outputs[$name] .= $stderr;

                if ($onOutput !== null) {
                    $onOutput(Process::ERR, $name, $stderr);
                }
            }
        }
    }
}
