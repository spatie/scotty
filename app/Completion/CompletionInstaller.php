<?php

namespace App\Completion;

use Phar;
use ReflectionClass;
use Symfony\Component\Console\Command\CompleteCommand;

class CompletionInstaller
{
    public const INSTALLED = 'installed';

    public const ALREADY = 'already';

    public const FAILED = 'failed';

    protected const MARKER = '# scotty shell completion';

    /** @var array<string> */
    protected array $supportedShells = ['bash', 'zsh', 'fish'];

    /** @return array<string> */
    public function supportedShells(): array
    {
        return $this->supportedShells;
    }

    public function isSupported(string $shell): bool
    {
        return in_array($shell, $this->supportedShells, true);
    }

    public function detectShell(): ?string
    {
        $shell = basename(getenv('SHELL') ?: '');

        return $this->isSupported($shell) ? $shell : null;
    }

    public function completionScript(string $shell): ?string
    {
        $resourceDir = dirname((new ReflectionClass(CompleteCommand::class))->getFileName()).'/../Resources';
        $file = "{$resourceDir}/completion.{$shell}";

        if (! file_exists($file)) {
            return null;
        }

        return str_replace(
            ['{{ COMMAND_NAME }}', '{{ VERSION }}'],
            [$this->commandName(), CompleteCommand::COMPLETION_API_VERSION],
            (string) file_get_contents($file),
        );
    }

    /**
     * Append the completion hook to the shell's rc file. Idempotent: a second
     * call returns ALREADY without writing again.
     */
    public function install(string $shell): string
    {
        $rcFile = $this->rcFile($shell);
        $line = $this->sourceLine($shell);

        $existing = file_exists($rcFile) ? (string) file_get_contents($rcFile) : '';

        // Key idempotency on the marker, not the exact line, so a moved or
        // renamed binary never appends a second block.
        if (str_contains($existing, self::MARKER)) {
            return self::ALREADY;
        }

        if (! $this->ensureDirectory(dirname($rcFile))) {
            return self::FAILED;
        }

        $block = ($existing !== '' && ! str_ends_with($existing, "\n") ? "\n" : '')
            ."\n".self::MARKER."\n{$line}\n";

        if (@file_put_contents($rcFile, $block, FILE_APPEND) === false) {
            return self::FAILED;
        }

        return self::INSTALLED;
    }

    /**
     * Wire completion automatically on the first interactive run. Attempts
     * exactly once (guarded by a sentinel) so a user who later removes the
     * hook is never nagged again. Returns the rc file path when it installed
     * the hook this call, or null when it did nothing.
     */
    public function autoInstall(): ?string
    {
        if ($this->homeDirectory() === '') {
            return null;
        }

        $shell = $this->detectShell();

        if ($shell === null) {
            return null;
        }

        if ($this->autoInstallAttempted()) {
            return null;
        }

        $result = $this->install($shell);

        // Only record the attempt once it didn't fail, so a transient failure
        // (e.g. a read-only rc file) is retried on a later run instead of
        // silently disabling completion forever.
        if ($result === self::FAILED) {
            return null;
        }

        $this->markAutoInstallAttempted();

        return $result === self::INSTALLED ? $this->rcFile($shell) : null;
    }

    protected function sentinelPath(): string
    {
        return $this->homeDirectory().'/.config/scotty/.completion-auto';
    }

    protected function autoInstallAttempted(): bool
    {
        return file_exists($this->sentinelPath());
    }

    protected function markAutoInstallAttempted(): void
    {
        $this->ensureDirectory(dirname($this->sentinelPath()));
        @touch($this->sentinelPath());
    }

    public function rcFile(string $shell): string
    {
        $home = $this->homeDirectory();

        return match ($shell) {
            'zsh' => "{$home}/.zshrc",
            'fish' => "{$home}/.config/fish/config.fish",
            default => "{$home}/.bashrc",
        };
    }

    public function sourceLine(string $shell): string
    {
        $binary = $this->binaryPath();

        if ($shell === 'fish') {
            return "{$binary} completion fish | source";
        }

        return "eval \"$({$binary} completion {$shell})\"";
    }

    public function displayPath(string $path): string
    {
        $home = $this->homeDirectory();

        if ($home !== '' && str_starts_with($path, $home)) {
            return '~'.substr($path, strlen($home));
        }

        return $path;
    }

    public function binaryPath(): string
    {
        $phar = Phar::running(false);

        if ($phar !== '') {
            return $phar;
        }

        $argv = $_SERVER['argv'][0] ?? 'scotty';

        return realpath($argv) ?: $argv;
    }

    protected function commandName(): string
    {
        return basename($_SERVER['argv'][0] ?? 'scotty');
    }

    public function homeDirectory(): string
    {
        return rtrim(getenv('HOME') ?: '', '/');
    }

    protected function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        return @mkdir($directory, 0755, true);
    }
}
