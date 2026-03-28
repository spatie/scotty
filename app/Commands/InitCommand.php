<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InitCommand extends Command
{
    protected $signature = 'init';

    protected $description = 'Create a new Scotty file';

    public function handle(): int
    {
        $format = select(
            label: 'Which format?',
            options: ['bash' => 'Bash (Scotty.sh)', 'blade' => 'Blade (Scotty.blade.php)'],
            default: 'bash',
        );

        $filename = $format === 'bash' ? 'Scotty.sh' : 'Scotty.blade.php';

        if (file_exists($filename)) {
            error("{$filename} already exists.");

            return 1;
        }

        $host = text(
            label: 'Server host',
            placeholder: 'user@hostname',
            required: true,
        );

        $content = match ($format) {
            'bash' => $this->bashTemplate($host),
            default => $this->bladeTemplate($host),
        };

        file_put_contents($filename, $content);

        info("Created {$filename}");

        return 0;
    }

    protected function bashTemplate(string $host): string
    {
        return <<<BASH
        #!/usr/bin/env scotty

        # @servers local=127.0.0.1 remote={$host}
        # @macro deploy startDeployment deploy

        BRANCH="main"

        # @task on:local
        startDeployment() {
            git checkout \$BRANCH
            git pull origin \$BRANCH
        }

        # @task on:remote
        deploy() {
            cd /home/forge/myapp
            git pull origin \$BRANCH
            php artisan migrate --force
        }
        BASH;
    }

    protected function bladeTemplate(string $host): string
    {
        return <<<'BLADE'
        @servers(['local' => '127.0.0.1', 'remote' => '%s'])

        @task('deploy', ['on' => 'remote'])
            cd /home/forge/myapp
            git pull origin {{ $branch }}
            php artisan migrate --force
        @endtask
        BLADE;
    }
}
