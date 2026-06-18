<?php

namespace App\Providers;

use App\Completion\CompletionInstaller;
use App\Services\ScottyDescriber;
use Illuminate\Support\ServiceProvider;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;
use Phar;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->singleton(DescriberContract::class, ScottyDescriber::class);

        $this->autoInstallShellCompletion();
    }

    /**
     * On the first interactive run of the distributed phar, wire shell
     * completion into the user's shell config so `scotty run <TAB>` works
     * without any setup command. Runs at most once (see CompletionInstaller).
     */
    protected function autoInstallShellCompletion(): void
    {
        if (Phar::running(false) === '') {
            return;
        }

        if (! stream_isatty(STDIN) || ! stream_isatty(STDOUT)) {
            return;
        }

        // Never run while the shell is asking us for completions, or when the
        // user is managing completion themselves.
        if (in_array($this->invokedCommandName(), ['_complete', 'completion'], true)) {
            return;
        }

        $rcFile = $this->app->make(CompletionInstaller::class)->autoInstall();

        if ($rcFile === null) {
            return;
        }

        $check = stream_isatty(STDERR) ? "\033[32m✓\033[0m " : '';
        fwrite(STDERR, "{$check}Shell completion enabled. Restart your shell to use it (scotty run <TAB>).\n");
    }

    /**
     * The command name as invoked, skipping any leading global options
     * (e.g. `scotty --no-interaction _complete` still resolves to `_complete`).
     */
    protected function invokedCommandName(): string
    {
        foreach (array_slice($_SERVER['argv'] ?? [], 1) as $argument) {
            if (! str_starts_with($argument, '-')) {
                return $argument;
            }
        }

        return '';
    }
}
