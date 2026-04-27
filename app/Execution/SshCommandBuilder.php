<?php

namespace App\Execution;

use App\Parsing\ServerDefinition;
use App\Ssh\SshConfigFile;
use Symfony\Component\Process\Process;

class SshCommandBuilder
{
    protected ?SshConfigFile $sshConfig = null;

    protected bool $sshConfigLoaded = false;

    /** @param array<string, string> $env */
    public function buildProcess(string $host, string $script, array $env = []): Process
    {
        $env['ENVOY_HOST'] = $host;
        [$bareHost, $port] = $this->extractPort($host);
        $target = $this->resolveHost($bareHost);

        if (ServerDefinition::isLocalHost($target)) {
            return Process::fromShellCommandline($script, null, $env)
                ->setTimeout(null);
        }

        $command = $this->buildSshCommand($target, $port, $script, $env);

        return Process::fromShellCommandline($command)
            ->setTimeout(null);
    }

    /** @param array<string, string> $env */
    public function buildCommand(string $host, string $script, array $env = []): string
    {
        $env['ENVOY_HOST'] = $host;
        [$bareHost, $port] = $this->extractPort($host);
        $target = $this->resolveHost($bareHost);

        if (ServerDefinition::isLocalHost($target)) {
            return $script;
        }

        return $this->buildSshCommand($target, $port, $script, $env);
    }

    /** @return array{string, ?int} */
    protected function extractPort(string $host): array
    {
        if (preg_match('/^(.+):(\d+)$/', $host, $match)) {
            return [$match[1], (int) $match[2]];
        }

        return [$host, null];
    }

    protected function resolveHost(string $host): string
    {
        $this->loadSshConfig();

        if ($this->sshConfig === null) {
            return $host;
        }

        return $this->sshConfig->findConfiguredHost($host) ?? $host;
    }

    /** @param array<string, string> $env */
    protected function buildSshCommand(string $target, ?int $port, string $script, array $env): string
    {
        $delimiter = 'EOF-SCOTTY';
        $portFlag = $port !== null ? "-p {$port} " : '';

        $exports = [];

        foreach ($env as $key => $value) {
            if ($value !== '') {
                $exports[] = "export {$key}=\"{$value}\"";
            }
        }

        $parts = [
            "ssh {$portFlag}{$target} 'bash -se' << \\{$delimiter}",
            ...$exports,
            'set -e',
            $script,
            $delimiter,
        ];

        return implode(PHP_EOL, $parts);
    }

    protected function loadSshConfig(): void
    {
        if ($this->sshConfigLoaded) {
            return;
        }

        $this->sshConfigLoaded = true;

        $configPath = $this->getSshConfigPath();

        if ($configPath === null) {
            return;
        }

        $this->sshConfig = SshConfigFile::parse($configPath);
    }

    protected function getSshConfigPath(): ?string
    {
        $systemUser = $this->getSystemUser();

        $home = match (true) {
            PHP_OS_FAMILY === 'Darwin' => getenv('HOME') ?: "/Users/{$systemUser}",
            PHP_OS_FAMILY === 'Windows' => getenv('USERPROFILE') ?: "C:\\Users\\{$systemUser}",
            default => getenv('HOME') ?: "/home/{$systemUser}",
        };

        $path = "{$home}/.ssh/config";

        if (! file_exists($path)) {
            return null;
        }

        return $path;
    }

    protected function getSystemUser(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('USERNAME') ?: '';
        }

        return posix_getpwuid(posix_geteuid())['name'] ?? '';
    }
}
