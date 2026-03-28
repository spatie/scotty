<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
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

        $parser = $this->resolveParser($filePath);
        $config = $parser->parse($filePath);

        $available = $config->availableTargets();

        $this->newLine();

        if ($available['macros'] !== []) {
            $this->output->writeln('  <options=bold>Macros</>');
            $this->newLine();

            foreach ($available['macros'] as $name) {
                $macro = $config->getMacro($name);
                $taskList = array_map(function (string $taskName) use ($config) {
                    $task = $config->getTask($taskName);

                    if ($task === null) {
                        return $taskName;
                    }

                    return $task->displayNameWithEmoji();
                }, $macro->tasks);

                $this->output->writeln("  <fg=green>{$name}</>");

                foreach ($taskList as $index => $taskDisplay) {
                    $number = $index + 1;
                    $this->output->writeln("    <fg=#4A5568>{$number}.</> {$taskDisplay}");
                }

                $this->newLine();
            }
        }

        if ($available['tasks'] !== []) {
            $this->output->writeln('  <options=bold>Tasks</>');
            $this->newLine();

            foreach ($available['tasks'] as $name) {
                $task = $config->getTask($name);
                $servers = implode(', ', $task->servers);
                $parallel = $task->parallel ? ' <fg=cyan>parallel</>' : '';
                $displayName = $task->displayNameWithEmoji();

                $this->output->writeln("  {$displayName}  <fg=#4A5568>on {$servers}</>{$parallel}");
            }

            $this->newLine();
        }

        return 0;
    }
}
