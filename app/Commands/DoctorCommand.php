<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesScottyFile;
use App\Parsing\ParseResult;
use App\Parsing\ServerDefinition;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;
use Throwable;

class DoctorCommand extends Command
{
    use ResolvesScottyFile;

    protected $signature = 'doctor
        {--path= : Path to the Scotty file}
        {--conf= : Scotty filename}';

    protected $description = 'Validate environment and configuration before deploying';

    protected bool $hasFailures = false;

    protected const SSH_TIMEOUT = 5;

    protected const REMOTE_TOOLS_TIMEOUT = 10;

    public function handle(): int
    {
        $this->newLine();
        $this->output->writeln('  <options=bold>Scotty Doctor</>');
        $this->output->writeln('  <fg=#4A5568>Checking your configuration, servers, and remote tools.</>');
        $this->newLine();

        $filePath = $this->checkScottyFileExists();

        if ($filePath === null) {
            return 1;
        }

        $config = $this->checkFileParsesSuccessfully($filePath);

        if ($config === null) {
            return 1;
        }

        $this->checkServersAreDefined($config);
        $this->checkTasksAreDefined($config);
        $this->checkMacroTasksExist($config);

        $this->newLine();
        $this->checkServers($config);

        $this->newLine();

        if ($this->hasFailures) {
            $this->output->writeln('  <fg=red;options=bold>Some checks failed.</> Fix the issues above and run <options=bold>scotty doctor</> again.');
            $this->newLine();

            return 1;
        }

        $this->output->writeln('  <fg=green;options=bold>Everything looks good.</> You\'re ready to deploy.');
        $this->newLine();

        return 0;
    }

    protected function checkScottyFileExists(): ?string
    {
        $filePath = $this->resolveFilePath();

        if ($filePath === null) {
            $this->writeFailure('No Scotty file found');
            $this->hasFailures = true;

            return null;
        }

        $this->writeSuccess("Scotty file found ({$filePath})");

        return $filePath;
    }

    protected function checkFileParsesSuccessfully(string $filePath): ?ParseResult
    {
        try {
            $parser = $this->resolveParser($filePath);
            $config = $parser->parse($filePath);
        } catch (Throwable $exception) {
            $this->writeFailure("File parsing failed: {$exception->getMessage()}");
            $this->hasFailures = true;

            return null;
        }

        $taskCount = count($config->tasks);
        $macroCount = count($config->macros);

        $this->writeSuccess("File parsed successfully ({$taskCount} tasks, {$macroCount} macros)");

        return $config;
    }

    protected function checkServersAreDefined(ParseResult $config): void
    {
        if ($config->servers === []) {
            $this->writeFailure('No servers defined');
            $this->hasFailures = true;

            return;
        }

        $serverCount = count($config->servers);

        $this->writeSuccess("{$serverCount} server(s) defined");
    }

    protected function checkTasksAreDefined(ParseResult $config): void
    {
        if ($config->tasks === []) {
            $this->writeFailure('No tasks defined');
            $this->hasFailures = true;

            return;
        }

        $taskCount = count($config->tasks);

        $this->writeSuccess("{$taskCount} task(s) defined");
    }

    protected function checkMacroTasksExist(ParseResult $config): void
    {
        if ($config->macros === []) {
            return;
        }

        $invalidReferences = $this->findInvalidMacroReferences($config);

        if ($invalidReferences !== []) {
            foreach ($invalidReferences as $reference) {
                $this->writeFailure($reference);
            }

            $this->hasFailures = true;

            return;
        }

        $this->writeSuccess('All macro tasks exist');
    }

    /** @return array<string> */
    protected function findInvalidMacroReferences(ParseResult $config): array
    {
        $invalid = [];

        foreach ($config->macros as $macro) {
            foreach ($macro->tasks as $taskName) {
                if ($config->getTask($taskName) !== null) {
                    continue;
                }

                $invalid[] = "Macro \"{$macro->name}\" references undefined task \"{$taskName}\"";
            }
        }

        return $invalid;
    }

    protected function checkServers(ParseResult $config): void
    {
        if ($config->servers === []) {
            return;
        }

        $this->output->writeln('  <options=bold>Servers</>');
        $this->output->writeln('  <fg=#4A5568>Testing SSH connectivity to each remote server.</>');

        /** @var array<ServerDefinition> $reachableRemoteServers */
        $reachableRemoteServers = [];

        foreach ($config->servers as $server) {
            if ($server->isLocal()) {
                $this->writeSuccess("{$server->name} ({$server->host}) — skipped (local)");

                continue;
            }

            $reachable = $this->checkSshConnectivity($server);

            if ($reachable) {
                $reachableRemoteServers[] = $server;
            }
        }

        foreach ($reachableRemoteServers as $server) {
            $this->newLine();
            $this->checkRemoteTools($server);
        }
    }

    protected function checkSshConnectivity(ServerDefinition $server): bool
    {
        $startTime = microtime(true);

        $process = new Process([
            'ssh',
            '-o', 'ConnectTimeout=5',
            '-o', 'BatchMode=yes',
            $server->host,
            'echo ok',
        ]);

        $process->setTimeout(self::SSH_TIMEOUT);

        try {
            $process->run();
        } catch (Throwable $exception) {
            $this->writeFailure("{$server->name} ({$server->host}) — connection timed out");
            $this->hasFailures = true;

            return false;
        }

        if (! $process->isSuccessful()) {
            $this->writeFailure("{$server->name} ({$server->host}) — connection failed");
            $this->hasFailures = true;

            return false;
        }

        $duration = round(microtime(true) - $startTime, 1);

        $this->writeSuccess("{$server->name} ({$server->host}) — connected in {$duration}s");

        return true;
    }

    protected function checkRemoteTools(ServerDefinition $server): void
    {
        $this->output->writeln("  <options=bold>Remote tools on {$server->name}</>");
        $this->output->writeln("  <fg=#4A5568>Checking which tools are available on {$server->host}.</>");

        $toolCheckScript = implode('; ', [
            'php -v 2>/dev/null | head -1',
            'composer --version 2>/dev/null',
            'node -v 2>/dev/null',
            'npm -v 2>/dev/null',
            'git --version 2>/dev/null',
        ]);

        $process = new Process([
            'ssh',
            '-o', 'ConnectTimeout=5',
            '-o', 'BatchMode=yes',
            $server->host,
            $toolCheckScript,
        ]);

        $process->setTimeout(self::REMOTE_TOOLS_TIMEOUT);

        try {
            $process->run();
        } catch (Throwable $exception) {
            $this->writeFailure("Could not check remote tools: {$exception->getMessage()}");
            $this->hasFailures = true;

            return;
        }

        $output = trim($process->getOutput());
        $lines = $output !== '' ? explode("\n", $output) : [];

        $this->reportToolVersion('php', $this->extractPhpVersion($lines));
        $this->reportToolVersion('composer', $this->extractComposerVersion($lines));
        $this->reportToolVersion('node', $this->extractNodeVersion($lines));
        $this->reportToolVersion('npm', $this->extractNpmVersion($lines));
        $this->reportToolVersion('git', $this->extractGitVersion($lines));
    }

    /** @param array<string> $lines */
    protected function extractPhpVersion(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^PHP (\d+\.\d+\.\d+)/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /** @param array<string> $lines */
    protected function extractComposerVersion(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/Composer.*?(\d+\.\d+\.\d+)/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /** @param array<string> $lines */
    protected function extractNodeVersion(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^v(\d+\.\d+\.\d+)/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /** @param array<string> $lines */
    protected function extractNpmVersion(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^(\d+\.\d+\.\d+)$/', trim($line), $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /** @param array<string> $lines */
    protected function extractGitVersion(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/git version (\d+\.\d+\.\d+)/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function reportToolVersion(string $tool, ?string $version): void
    {
        if ($version === null) {
            $this->output->writeln("  <fg=#4A5568>-</> {$tool} not found");

            return;
        }

        $this->writeSuccess("{$tool} {$version}");
    }

    protected function writeSuccess(string $message): void
    {
        $this->output->writeln("  <fg=green>✓</> {$message}");
    }

    protected function writeFailure(string $message): void
    {
        $this->output->writeln("  <fg=red>✗</> {$message}");
    }
}
