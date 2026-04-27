<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
use App\Parsing\ParseResult;
use LaravelZero\Framework\Commands\Command;

class TasksCommand extends Command
{
    use ResolvesScottyFile;

    protected $signature = 'tasks
        {--path= : Path to the Scotty file}
        {--conf= : Scotty filename}';

    protected $description = 'List all available tasks and macros';

    public function handle(): int
    {
        $filePath = $this->resolveFilePathOrFail();

        if ($filePath === null) {
            return 1;
        }

        $config = $this->resolveParser($filePath)->parse($filePath);
        $available = $config->availableTargets();

        $this->newLine();

        if ($available['macros'] !== []) {
            $this->renderMacros($config, $available['macros']);
        }

        if ($available['tasks'] !== []) {
            $this->renderTasks($config, $available['tasks']);
        }

        return 0;
    }

    /** @param array<string> $macroNames */
    protected function renderMacros(ParseResult $config, array $macroNames): void
    {
        $this->output->writeln('  <options=bold>Macros</>');
        $this->newLine();

        foreach ($macroNames as $name) {
            $macro = $config->getMacro($name);

            $this->output->writeln("  <fg=green>{$name}</>");

            foreach ($macro->tasks as $index => $taskName) {
                $task = $config->getTask($taskName);
                $taskDisplay = $task !== null ? $task->displayNameWithEmoji() : $taskName;
                $number = $index + 1;

                $this->output->writeln("    <fg=#4A5568>{$number}.</> {$taskDisplay}");
            }

            $this->newLine();
        }
    }

    /** @param array<string> $taskNames */
    protected function renderTasks(ParseResult $config, array $taskNames): void
    {
        $this->output->writeln('  <options=bold>Tasks</>');
        $this->newLine();

        foreach ($taskNames as $name) {
            $task = $config->getTask($name);
            $servers = implode(', ', $task->servers);
            $parallel = $task->parallel ? ' <fg=cyan>parallel</>' : '';
            $displayName = $task->displayNameWithEmoji();

            $this->output->writeln("  {$displayName}  <fg=#4A5568>on {$servers}</>{$parallel}");
        }

        $this->newLine();
    }
}
