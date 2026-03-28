<?php

namespace App\Commands;

use App\Parsing\BashParser;
use App\Parsing\BladeParser;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class TasksCommand extends Command
{
    protected $signature = 'tasks
        {--path= : Path to the Scotty file}
        {--conf= : Scotty filename}';

    protected $description = 'List all available tasks and macros';

    public function handle(): int
    {
        $filePath = $this->resolveFilePath();

        if ($filePath === null) {
            error('No Scotty file found.');

            return 1;
        }

        $parser = str_ends_with($filePath, '.sh') ? new BashParser : new BladeParser;
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
                $rows[] = [$name, $servers, $flags];
            }

            table(['Task', 'Servers', 'Flags'], $rows);
        }

        return 0;
    }

    protected function resolveFilePath(): ?string
    {
        if ($path = $this->option('path')) {
            return file_exists($path) ? $path : null;
        }

        if ($filename = $this->option('conf')) {
            return file_exists($filename) ? $filename : null;
        }

        if (file_exists('Scotty.sh')) {
            return 'Scotty.sh';
        }

        if (file_exists('Envoy.sh')) {
            return 'Envoy.sh';
        }

        if (file_exists('Scotty.blade.php')) {
            return 'Scotty.blade.php';
        }

        if (file_exists('Envoy.blade.php')) {
            return 'Envoy.blade.php';
        }

        return null;
    }
}
