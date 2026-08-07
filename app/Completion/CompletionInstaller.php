<?php

namespace App\Completion;

use Phar;

class CompletionInstaller
{
    protected string $marker = '# scotty shell completion';

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

    /**
     * Append the completion hook to the shell's rc file. Idempotent: a second
     * call returns Already without writing again.
     */
    public function install(string $shell): InstallResult
    {
        $rcFile = $this->rcFile($shell);

        $existing = is_file($rcFile) ? (string) file_get_contents($rcFile) : '';

        // Key idempotency on the marker, not the exact line, so a moved or
        // renamed binary never appends a second block.
        if (str_contains($existing, $this->marker)) {
            return InstallResult::Already;
        }

        if (! $this->ensureDirectory(dirname($rcFile))) {
            return InstallResult::Failed;
        }

        $block = ($existing !== '' && ! str_ends_with($existing, "\n") ? "\n" : '')
            ."\n{$this->marker}\n{$this->sourceLine($shell)}\n";

        if (@file_put_contents($rcFile, $block, FILE_APPEND) === false) {
            return InstallResult::Failed;
        }

        return InstallResult::Installed;
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

        $sentinel = $this->homeDirectory().'/.config/scotty/.completion-auto';

        if (file_exists($sentinel)) {
            return null;
        }

        $result = $this->install($shell);

        // Only record the attempt once it didn't fail, so a transient failure
        // (e.g. a read-only rc file) is retried on a later run instead of
        // silently disabling completion forever.
        if ($result === InstallResult::Failed) {
            return null;
        }

        $this->ensureDirectory(dirname($sentinel));
        @touch($sentinel);

        return $result === InstallResult::Installed ? $this->rcFile($shell) : null;
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
