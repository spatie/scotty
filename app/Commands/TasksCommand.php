<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

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

        if ($available['macros'] !== []) {
            info('Macros');

            $rows = [];

            foreach ($available['macros'] as $name) {
                $macro = $config->getMacro($name);
                $rows[] = [$name, implode(', ', $macro->tasks)];
            }

            table(['Macro', 'Tasks'], $rows);
        }

        if ($available['tasks'] !== []) {
            info('Tasks');

            $rows = [];

            foreach ($available['tasks'] as $name) {
                $task = $config->getTask($name);
                $servers = implode(', ', $task->servers);
                $flags = $task->parallel ? 'parallel' : '';
                $rows[] = [$task->displayNameWithEmoji(), $servers, $flags];
            }

            table(['Task', 'Servers', 'Flags'], $rows);
        }

        return 0;
    }
}
