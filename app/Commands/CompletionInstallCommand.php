<?php

namespace App\Commands;

use App\Completion\CompletionInstaller;
use App\Completion\InstallResult;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class CompletionInstallCommand extends Command
{
    protected $signature = 'completion:install
        {shell? : The shell to install for (bash, zsh, fish), auto-detected if omitted}';

    protected $description = 'Add the shell completion hook to your shell config';

    public function handle(CompletionInstaller $installer): int
    {
        $shell = $this->argument('shell') ?? $installer->detectShell();

        if ($shell === null || ! $installer->isSupported($shell)) {
            error('Could not detect a supported shell. Pass one explicitly: scotty completion:install '.implode('|', $installer->supportedShells()).'.');

            return 2;
        }

        info("Detected {$shell}.");

        $rcFile = $installer->displayPath($installer->rcFile($shell));

        return match ($installer->install($shell)) {
            InstallResult::Already => $this->reportAlreadyInstalled($rcFile),
            InstallResult::Installed => $this->reportInstalled($rcFile),
            InstallResult::Failed => $this->reportFailed($rcFile),
        };
    }

    protected function reportAlreadyInstalled(string $rcFile): int
    {
        note("Completion already installed in {$rcFile}.");

        return 0;
    }

    protected function reportInstalled(string $rcFile): int
    {
        info("Added completion to {$rcFile}.");
        note("Restart your shell (or run: source {$rcFile}), then: scotty run <TAB>");

        return 0;
    }

    protected function reportFailed(string $rcFile): int
    {
        error("Could not write to {$rcFile}.");

        return 1;
    }
}
