<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
use App\Parsing\ParseResult;
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

        $config = $this->resolveParser($filePath)->parse($filePath);

        if ($config->servers === []) {
            error('No servers defined.');

            return 1;
        }

        $hostOptions = $this->buildHostOptions($config->servers);

        $name = $this->argument('name');

        $host = $name !== null
            ? $this->resolveHostByServerName($config, $name, $hostOptions)
            : $this->promptForRemoteHost($hostOptions);

        if ($host === null) {
            return 1;
        }

        passthru("ssh {$host}");

        return 0;
    }

    /**
     * @param  array<string, ServerDefinition>  $servers
     * @return array<string, string>
     */
    protected function buildHostOptions(array $servers): array
    {
        $hostOptions = [];

        foreach ($servers as $server) {
            foreach ($server->hosts as $host) {
                $hostOptions["{$server->name} ({$host})"] = $host;
            }
        }

        return $hostOptions;
    }

    /** @param array<string, string> $hostOptions */
    protected function resolveHostByServerName(ParseResult $config, string $name, array $hostOptions): ?string
    {
        $server = $config->getServer($name);

        if ($server === null) {
            error("Server \"{$name}\" is not defined.");

            return null;
        }

        if ($server->isLocal()) {
            error('Cannot SSH into local server.');

            return null;
        }

        if (count($server->hosts) === 1) {
            return $server->hosts[0];
        }

        $serverHostOptions = array_filter(
            $hostOptions,
            fn (string $host) => in_array($host, $server->hosts, true),
        );

        $selected = select(
            label: 'Which host?',
            options: array_keys($serverHostOptions),
        );

        return $hostOptions[$selected];
    }

    /** @param array<string, string> $hostOptions */
    protected function promptForRemoteHost(array $hostOptions): ?string
    {
        $remoteOptions = array_filter(
            $hostOptions,
            fn (string $host) => ! ServerDefinition::isLocalHost($host),
        );

        if ($remoteOptions === []) {
            error('No remote servers defined.');

            return null;
        }

        if (count($remoteOptions) === 1) {
            return $remoteOptions[array_key_first($remoteOptions)];
        }

        $selected = select(
            label: 'Which server?',
            options: array_keys($remoteOptions),
        );

        return $remoteOptions[$selected];
    }
}
