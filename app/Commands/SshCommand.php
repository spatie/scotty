<?php

namespace App\Commands;

use App\Parsing\BashParser;
use App\Parsing\BladeParser;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\select;

class SshCommand extends Command
{
    protected $signature = 'ssh
        {name? : The server to connect to}
        {--path= : Path to the Scotty file}
        {--conf= : Scotty filename}';

    protected $description = 'SSH into a defined server';

    public function handle(): int
    {
        $filePath = $this->resolveFilePath();

        if ($filePath === null) {
            error('No Scotty file found.');

            return 1;
        }

        $parser = str_ends_with($filePath, '.sh') ? new BashParser : new BladeParser;
        $config = $parser->parse($filePath);

        $servers = $config->servers;

        if ($servers === []) {
            error('No servers defined.');

            return 1;
        }

        $name = $this->argument('name');

        if ($name === null) {
            if (count($servers) === 1) {
                $name = array_key_first($servers);
            } else {
                $name = select(
                    label: 'Which server?',
                    options: array_map(fn ($s) => "{$s->name} ({$s->host})", $servers),
                );
            }
        }

        $server = $config->getServer($name);

        if ($server === null) {
            error("Server \"{$name}\" is not defined.");

            return 1;
        }

        if ($server->isLocal()) {
            error('Cannot SSH into local server.');

            return 1;
        }

        passthru("ssh {$server->host}");

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
