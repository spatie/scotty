<?php

namespace App\Commands;

use App\Completion\CompletionInstaller;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class CompletionCommand extends Command
{
    protected $signature = 'completion
        {shell? : The shell to generate the script for (bash, zsh, fish), or "install" to set it up automatically}
        {target? : When using "install", the shell to install for (auto-detected if omitted)}';

    protected $description = 'Dump or install the shell completion script';

    public function handle(CompletionInstaller $installer): int
    {
        if ($this->argument('shell') === 'install') {
            return $this->install($installer, $this->argument('target'));
        }

        return $this->dump($installer, $this->argument('shell'));
    }

    protected function dump(CompletionInstaller $installer, ?string $shell): int
    {
        $shell ??= $installer->detectShell() ?? '';

        $script = $installer->completionScript($shell);

        if ($script === null) {
            // Route to STDERR: the rc hook is `eval "$(scotty completion <shell>)"`,
            // which captures STDOUT, so an error there would be eval'd by the shell.
            $this->writeError("Shell completion is not supported for \"{$shell}\" (supported: ".implode(', ', $installer->supportedShells()).').');

            return 2;
        }

        $this->output->write($script);

        return 0;
    }

    protected function install(CompletionInstaller $installer, ?string $shell): int
    {
        $shell ??= $installer->detectShell();

        if ($shell === null || ! $installer->isSupported($shell)) {
            error('Could not detect a supported shell. Pass one explicitly: scotty completion install bash|zsh|fish.');

            return 2;
        }

        info("Detected {$shell}.");

        $rcFile = $installer->displayPath($installer->rcFile($shell));

        switch ($installer->install($shell)) {
            case CompletionInstaller::ALREADY:
                note("Completion already installed in {$rcFile}.");

                return 0;

            case CompletionInstaller::INSTALLED:
                info("Added completion to {$rcFile}.");
                note("Restart your shell (or run: source {$rcFile}), then: scotty run <TAB>");

                return 0;

            default:
                error("Could not write to {$rcFile}.");

                return 1;
        }
    }

    protected function writeError(string $message): void
    {
        $output = $this->output->getOutput();

        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln("<error>{$message}</error>");

            return;
        }

        $this->output->writeln("<error>{$message}</error>");
    }
}
