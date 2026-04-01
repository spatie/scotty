<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
use App\Parsing\ServerDefinition;
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

        $hostOptions = [];

        foreach ($servers as $server) {
            foreach ($server->hosts as $host) {
                $hostOptions["{$server->name} ({$host})"] = $host;
            }
        }

        $name = $this->argument('name');

        if ($name !== null) {
            $server = $config->getServer($name);

            if ($server === null) {
                error("Server \"{$name}\" is not defined.");

                return 1;
            }

            if ($server->isLocal()) {
                error('Cannot SSH into local server.');

                return 1;
            }

            $host = count($server->hosts) === 1
                ? $server->hosts[0]
                : $hostOptions[select(
                    label: 'Which host?',
                    options: array_keys(array_filter($hostOptions, fn ($h) => in_array($h, $server->hosts))),
                )];
        } else {
            $remoteOptions = array_filter($hostOptions, fn ($host) => ! ServerDefinition::isLocalHost($host));

            if ($remoteOptions === []) {
                error('No remote servers defined.');

                return 1;
            }

            $selected = count($remoteOptions) === 1
                ? array_key_first($remoteOptions)
                : select(
                    label: 'Which server?',
                    options: array_keys($remoteOptions),
                );

            $host = $remoteOptions[$selected];
        }

        passthru("ssh {$host}");

        return 0;
    }
}
