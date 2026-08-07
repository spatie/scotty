<?php

namespace App\Providers;

use App\Completion\CompletionInstaller;
use App\Services\ScottyDescriber;
use Illuminate\Support\ServiceProvider;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;
use Phar;
use Symfony\Component\Console\Input\ArgvInput;

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
        $command = (new ArgvInput)->getFirstArgument() ?? '';

        if ($command === '_complete' || str_starts_with($command, 'completion')) {
            return;
        }

        $rcFile = (new CompletionInstaller)->autoInstall();

        if ($rcFile === null) {
            return;
        }

        fwrite(STDERR, "Shell completion enabled. Restart your shell to use it (scotty run <TAB>).\n");
    }
}
