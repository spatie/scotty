<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\select;

class SshCommand extends Command
{
    use ResolvesScottyFile;

    protected $signature = 'ssh
        {name? : The server to connect to}
        {--path= : Path to the Scotty file}
        {--conf= : Scotty filename}';

    protected $description = 'SSH into a defined server';

    public function handle(): int
    {
        $filePath = $this->resolveFilePathOrFail();

        if ($filePath === null) {
            return 1;
        }

        $parser = $this->resolveParser($filePath);
        $config = $parser->parse($filePath);

        $servers = $config->servers;

        if ($servers === []) {
            error('No servers defined.');

            return 1;
        }

        $name = $this->argument('name');

        if ($name === null) {
            $name = count($servers) === 1
                ? array_key_first($servers)
                : select(
                    label: 'Which server?',
                    options: array_map(fn ($server) => "{$server->name} ({$server->host})", $servers),
                );
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
}
