<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
use App\Execution\Executor;
use App\Execution\TaskResult;
use App\Parsing\ParseResult;
use App\Parsing\TaskDefinition;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class RunCommand extends Command
{
    use ResolvesScottyFile;

    protected $signature = 'run
        {task : The task or macro to run}
        {--continue : Continue on failure}
        {--pretend : Dump the script instead of running it}
        {--path= : Path to the Scotty file}
        {--conf= : Scotty filename}
        {--summary : Only show task results, hide output}';

    protected $description = 'Run a task or macro';

    protected const TRACE_MARKER = 'ENVOY_TRACE:';

    /** @var array<string, string> */
    protected array $serverColors = [];

    protected int $colorIndex = 0;

    /** @var array<string> */
    protected array $colors = ['yellow', 'cyan', 'magenta', 'blue', 'green'];

    /** @var array<array{string, string, string, string}> */
    protected array $timings = [];

    protected bool $failed = false;

    protected float $taskStartTime = 0;

    protected float $globalStartTime = 0;

    protected bool $spinnerLineVisible = false;

    /** @var array<string> */
    protected array $spinnerFrames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    protected int $spinnerIndex = 0;

    protected bool $pauseRequested = false;

    protected string $currentTaskName = '';

    protected int $currentStep = 0;

    protected int $totalSteps = 0;

    protected string $lastTracedCommand = '';

    public function handle(): int
    {
        $filePath = $this->resolveFilePathOrFail();

        if ($filePath === null) {
            return 1;
        }

        $parser = $this->resolveParser($filePath);
        $dynamicOptions = $this->gatherDynamicOptions();
        $config = $parser->parse($filePath, $dynamicOptions);

        $target = $this->argument('task');
        $tasks = $config->resolveTasksForTarget($target);

        if ($tasks === []) {
            $this->showAvailableTargets($config);

            return 1;
        }

        foreach ($tasks as $task) {
            if ($task->confirm !== null && ! confirm($task->confirm)) {
                warning('Task cancelled.');

                return 1;
            }
        }

        $showSummaryOnly = $this->option('summary');
        $pretend = $this->option('pretend');

        $this->totalSteps = count($tasks);
        $this->globalStartTime = microtime(true);

        $this->enablePauseDetection();
        $this->registerSignalHandlers();

        $this->newLine();
        $this->output->writeln("  <options=bold>Starting {$target}</>");
        $this->newLine();

        $executor = new Executor;

        $results = $executor->run(
            target: $target,
            config: $config,
            env: $dynamicOptions,
            continueOnError: $this->option('continue'),
            pretend: $pretend,
            onTaskStart: function (TaskDefinition $task, int $index, int $total): void {
                $this->handlePauseBetweenTasks();

                $this->currentTaskName = $task->name;
                $this->currentStep = $index + 1;
                $this->taskStartTime = microtime(true);
                $this->lastTracedCommand = '';

                $servers = implode(', ', $task->servers);
                $parallel = $task->parallel ? ' <fg=cyan>parallel</>' : '';

                $this->clearSpinnerLine();
                $this->output->writeln("  <fg=blue>●</> <options=bold>{$task->name}</> <fg=#4A5568>[{$this->currentStep}/{$total}] on {$servers}</>{$parallel}");
            },
            onTaskOutput: $showSummaryOnly ? null : function (string $type, string $serverName, string $output): void {
                $this->checkForPauseInput();
                $this->clearSpinnerLine();
                $this->writeTaskOutput($type, $serverName, $output);
                $this->writeSpinnerLine();
            },
            onTick: function (): void {
                $this->checkForPauseInput();
                $this->overwriteSpinnerLine();
            },
            onTaskComplete: function (TaskDefinition $task, TaskResult $result) use ($showSummaryOnly, $pretend): void {
                $this->clearSpinnerLine();
                $this->writeTaskComplete($task, $result, $showSummaryOnly, $pretend);
            },
        );

        $this->clearSpinnerLine();
        $this->disablePauseDetection();
        $this->writeResultSummary($results);

        return $this->failed ? 1 : 0;
    }

    protected function buildSpinnerContent(): string
    {
        $frame = $this->spinnerFrames[$this->spinnerIndex % count($this->spinnerFrames)];
        $this->spinnerIndex++;

        $elapsed = $this->formatDuration(microtime(true) - $this->taskStartTime);

        $line = "  <fg=#4A5568>│</>  <fg=blue>{$frame}</>  <fg=#4A5568>{$elapsed}</>";

        if ($this->lastTracedCommand !== '') {
            $termWidth = (int) (@exec('tput cols') ?: 120);
            $usedLength = strlen("  |  {$frame}  {$elapsed}  >   p pause  ^C quit");
            $availableForCommand = $termWidth - $usedLength;

            if ($availableForCommand > 15) {
                $command = $this->truncate($this->lastTracedCommand, $availableForCommand);
                $line .= "  <fg=#4A5568>▸ {$command}</>";
            }
        }

        if ($this->pauseRequested) {
            $line .= '  <fg=yellow>⏸ pausing after this task</>';
        }

        return $line;
    }

    protected function writeSpinnerLine(): void
    {
        $line = $this->buildSpinnerContent();
        $hintsLine = '  <fg=#4A5568>p pause  ^C quit</>';

        $this->output->write($line . "\n\n" . $hintsLine);
        $this->spinnerLineVisible = true;
    }

    protected function overwriteSpinnerLine(): void
    {
        if (! $this->spinnerLineVisible) {
            $this->writeSpinnerLine();

            return;
        }

        $line = $this->buildSpinnerContent();
        $hintsLine = '  <fg=#4A5568>p pause  ^C quit</>';

        $this->output->write("\r\033[2A\r" . $line . "\n\n" . $hintsLine . "\033[K");
    }

    protected function clearSpinnerLine(): void
    {
        if (! $this->spinnerLineVisible) {
            return;
        }

        $this->output->write("\r\033[2K\033[1A\033[2K\033[1A\033[2K");
        $this->spinnerLineVisible = false;
    }

    protected function writeTaskOutput(string $type, string $serverName, string $output): void
    {
        $lines = explode("\n", rtrim($output));
        $color = $this->getServerColor($serverName);

        foreach ($lines as $line) {
            if (trim($line) === '' || $this->isSshWarning($line)) {
                continue;
            }

            if ($type === Process::ERR && str_contains($line, self::TRACE_MARKER)) {
                $command = $this->extractTraceCommand($line);

                if ($command !== null) {
                    $this->lastTracedCommand = $command;
                }

                continue;
            }

            $cleanLine = $this->cleanOutputLine($line);

            $this->output->writeln("  <fg=#4A5568>│</>  <fg={$color}>{$serverName}</>  {$cleanLine}");
        }
    }

    protected function writeTaskComplete(TaskDefinition $task, TaskResult $result, bool $showSummaryOnly, bool $pretend): void
    {
        $duration = $this->formatDuration($result->duration);
        $servers = implode(', ', $task->servers);

        if ($pretend) {
            foreach ($result->outputs as $output) {
                $this->output->writeln($output);
            }

            $this->timings[] = [$task->name, $servers, '-', '<fg=#4A5568>pretend</>'];
            $this->newLine();

            return;
        }

        if ($result->succeeded()) {
            $this->output->writeln("  <fg=green>✓ {$task->name}</> <fg=#4A5568>{$duration}</>");
            $this->newLine();

            $this->timings[] = [$task->name, $servers, $duration, '<fg=green>OK</>'];

            return;
        }

        $this->failed = true;

        $this->output->writeln("  <fg=red>✗ {$task->name}</> <fg=#4A5568>{$duration}</>");

        if ($showSummaryOnly) {
            $this->newLine();

            foreach ($result->outputs as $hostName => $output) {
                foreach (explode("\n", rtrim($output)) as $line) {
                    if (trim($line) === '' || $this->isSshWarning($line) || str_contains($line, self::TRACE_MARKER)) {
                        continue;
                    }

                    $this->output->writeln("    <fg=#4A5568>{$hostName}</>  {$line}");
                }
            }
        }

        if ($result->failedHost !== null) {
            $this->output->writeln("  <fg=red>  └ failed on {$result->failedHost}</>");
        }

        $this->newLine();

        $this->timings[] = [$task->name, $servers, $duration, '<fg=red>FAILED</>'];
    }

    /** @param array<string, TaskResult> $results */
    protected function writeResultSummary(array $results): void
    {
        if ($results === []) {
            return;
        }

        table(['Task', 'Server', 'Duration', 'Status'], $this->timings);

        $totalDuration = $this->formatDuration(
            array_sum(array_map(fn (TaskResult $taskResult) => $taskResult->duration, $results))
        );

        $totalCount = count($results);

        if (! $this->failed) {
            $this->output->writeln("  <fg=green;options=bold>✓ All {$totalCount} tasks completed in {$totalDuration}</>");
            $this->newLine();

            return;
        }

        $failedTask = array_key_first(array_filter($results, fn (TaskResult $taskResult) => ! $taskResult->succeeded()));
        $this->output->writeln("  <fg=red;options=bold>✗ Failed at {$failedTask}</>");
        $this->newLine();
    }

    protected function extractTraceCommand(string $line): ?string
    {
        $position = strpos($line, self::TRACE_MARKER);

        if ($position === false) {
            return null;
        }

        $command = trim(substr($line, $position + strlen(self::TRACE_MARKER)));

        if ($command === '') {
            return null;
        }

        if ($this->isTraceNoise($command)) {
            $this->lastTracedCommand = '';

            return null;
        }

        return $command;
    }

    protected function isTraceNoise(string $command): bool
    {
        if (preg_match('/^[A-Z_][A-Z0-9_]*=/', $command)) {
            return true;
        }

        if (preg_match('/^(echo|printf)\b/', $command)) {
            return true;
        }

        if (preg_match('/^(set|export|local|readonly|declare|trap)\b/', $command)) {
            return true;
        }

        if (preg_match('/^\[{1,2}\s/', $command) || str_starts_with($command, 'test ')) {
            return true;
        }

        if (str_starts_with($command, 'sleep ')) {
            return true;
        }

        return false;
    }

    protected function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGINT, function (): void {
            $this->clearSpinnerLine();
            $this->disablePauseDetection();

            $this->newLine();
            $this->output->writeln('  <fg=yellow;options=bold>Cancelled.</>');
            $this->newLine();

            exit(130);
        });

        pcntl_async_signals(true);
    }

    protected function enablePauseDetection(): void
    {
        if (! $this->isInteractive()) {
            return;
        }

        stream_set_blocking(STDIN, false);
        @shell_exec('stty -icanon -echo 2>/dev/null');
    }

    protected function disablePauseDetection(): void
    {
        if (! $this->isInteractive()) {
            return;
        }

        stream_set_blocking(STDIN, true);
        @shell_exec('stty sane 2>/dev/null');
    }

    protected function checkForPauseInput(): void
    {
        if (! $this->isInteractive()) {
            return;
        }

        $input = @fread(STDIN, 1);

        if ($input !== 'p' && $input !== 'P') {
            return;
        }

        $this->pauseRequested = true;
        $this->clearSpinnerLine();
        $this->writeSpinnerLine();
    }

    protected function handlePauseBetweenTasks(): void
    {
        $this->checkForPauseInput();

        if (! $this->pauseRequested) {
            return;
        }

        $this->clearSpinnerLine();
        $this->newLine();
        $this->output->writeln('  <fg=yellow;options=bold>⏸  Paused</> <fg=#4A5568>press Enter to continue, ^C to quit</>');

        while (true) {
            $input = @fread(STDIN, 1);

            if ($input === "\n" || $input === "\r" || $input === ' ') {
                break;
            }

            usleep(50_000);
        }

        $this->pauseRequested = false;

        $this->output->write("\033[2A\033[2K\033[1B\033[2K\033[1A");
        $this->output->writeln('  <fg=green>▶  Resumed</>');
        $this->newLine();
    }

    protected function isInteractive(): bool
    {
        return stream_isatty(STDIN);
    }

    protected function formatDuration(float $seconds): string
    {
        $rounded = (int) round($seconds);

        if ($rounded < 1) {
            return '0s';
        }

        if ($rounded < 60) {
            return "{$rounded}s";
        }

        $minutes = intdiv($rounded, 60);
        $remainingSeconds = $rounded % 60;

        return "{$minutes}m {$remainingSeconds}s";
    }

    protected function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1).'…';
    }

    protected function cleanOutputLine(string $line): string
    {
        if (str_starts_with($line, '-e ')) {
            $line = substr($line, 3);
        }

        return preg_replace('/\033\[[0-9;]*m/', '', $line);
    }

    /** @return array<string, string> */
    protected function gatherDynamicOptions(): array
    {
        $data = [];

        $argv = $_SERVER['argv'] ?? [];

        foreach ($argv as $argument) {
            if (! preg_match('/^--([a-zA-Z][\w-]*)=(.+)$/', $argument, $match)) {
                continue;
            }

            $key = $match[1];

            if (in_array($key, ['continue', 'pretend', 'path', 'conf', 'summary'])) {
                continue;
            }

            $data[$key] = $match[2];

            $camelCase = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $key))));
            $snakeCase = str_replace('-', '_', $key);

            $data[$camelCase] = $match[2];
            $data[$snakeCase] = $match[2];
        }

        return $data;
    }

    protected function showAvailableTargets(ParseResult $config): void
    {
        $target = $this->argument('task');

        error("Task or macro \"{$target}\" is not defined.");

        $available = $config->availableTargets();

        if ($available['tasks'] !== []) {
            $this->newLine();
            info('Available tasks:');

            foreach ($available['tasks'] as $name) {
                $this->output->writeln("  - {$name}");
            }
        }

        if ($available['macros'] !== []) {
            $this->newLine();
            info('Available macros:');

            foreach ($available['macros'] as $name) {
                $this->output->writeln("  - {$name}");
            }
        }
    }

    protected function getServerColor(string $name): string
    {
        if (! isset($this->serverColors[$name])) {
            $this->serverColors[$name] = $this->colors[$this->colorIndex % count($this->colors)];
            $this->colorIndex++;
        }

        return $this->serverColors[$name];
    }

    protected function isSshWarning(string $line): bool
    {
        return str_contains($line, 'Warning: Permanently added')
            || str_contains($line, 'Connection to')
            || str_contains($line, 'Warning: No xauth data');
    }
}
