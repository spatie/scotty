<?php

namespace App\Execution;

use App\Ssh\SshConfigFile;
use Symfony\Component\Process\Process;

class SshCommandBuilder
{
    protected ?SshConfigFile $sshConfig = null;

    public function __construct()
    {
        $configPath = $this->getSshConfigPath();

        if ($configPath !== null && file_exists($configPath)) {
            $this->sshConfig = SshConfigFile::parse($configPath);
        }
    }

    /**
     * Build a Symfony Process for executing a script on the given host.
     *
     * @param array<string, string> $env
     */
    public function buildProcess(string $host, string $script, array $env = []): Process
    {
        $target = $this->resolveHost($host);

        $env['ENVOY_HOST'] = $host;

        if ($this->isLocal($target)) {
            return Process::fromShellCommandline($script, null, $env)
                ->setTimeout(null);
        }

        $command = $this->buildSshCommand($target, $script, $env);

        return Process::fromShellCommandline($command)
            ->setTimeout(null);
    }

    /**
     * Build the full SSH command string with a heredoc.
     *
     * @param array<string, string> $env
     */
    public function buildCommand(string $host, string $script, array $env = []): string
    {
        $target = $this->resolveHost($host);

        $env['ENVOY_HOST'] = $host;

        if ($this->isLocal($target)) {
            return $script;
        }

        return $this->buildSshCommand($target, $script, $env);
    }

    public function isLocal(string $host): bool
    {
        return in_array($host, ['local', 'localhost', '127.0.0.1']);
    }

    /**
     * Resolve a host through the SSH config, if available.
     */
    protected function resolveHost(string $host): string
    {
        if ($this->sshConfig === null) {
            return $host;
        }

        return $this->sshConfig->findConfiguredHost($host) ?? $host;
    }

    /**
     * @param array<string, string> $env
     */
    protected function buildSshCommand(string $target, string $script, array $env): string
    {
        $delimiter = 'EOF-SCOTTY';

        $exports = [];
        foreach ($env as $key => $value) {
            if ($value !== false && $value !== '') {
                $exports[] = 'export ' . $key . '="' . $value . '"';
            }
        }

        $parts = [
            "ssh {$target} 'bash -se' << \\{$delimiter}",
            ...$exports,
            'set -e',
            $script,
            $delimiter,
        ];

        return implode(PHP_EOL, $parts);
    }

    protected function getSshConfigPath(): ?string
    {
        $home = match (true) {
            PHP_OS_FAMILY === 'Darwin' => getenv('HOME') ?: '/Users/' . $this->getSystemUser(),
            PHP_OS_FAMILY === 'Windows' => getenv('USERPROFILE') ?: 'C:\\Users\\' . $this->getSystemUser(),
            default => getenv('HOME') ?: '/home/' . $this->getSystemUser(),
        };

        $path = $home . '/.ssh/config';

        return file_exists($path) ? $path : null;
    }

    protected function getSystemUser(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('USERNAME') ?: '';
        }

        return posix_getpwuid(posix_geteuid())['name'] ?? '';
    }
}
