<?php

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->fixturePath = realpath(__DIR__.'/../fixtures');
});

it('runs a task in pretend mode', function () {
    $exitCode = Artisan::call('run', [
        'task' => 'pull',
        '--pretend' => true,
        '--conf' => $this->fixturePath.'/complete.sh',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('pull');
});

it('shows error for unknown task', function () {
    $exitCode = Artisan::call('run', [
        'task' => 'nonexistent',
        '--conf' => $this->fixturePath.'/complete.sh',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('not defined');
});

it('works with --conf option', function () {
    $exitCode = Artisan::call('run', [
        'task' => 'migrate',
        '--pretend' => true,
        '--conf' => $this->fixturePath.'/complete.sh',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('migrate');
});

it('runs local tasks without formatting errors', function () {
    $exitCode = Artisan::call('run', [
        'task' => 'deploy',
        '--conf' => $this->fixturePath.'/local-only.sh',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('hello from scotty')
        ->and($output)->toContain('finished')
        ->and($output)->toContain('Running deploy')
        ->and($output)->toContain('Greet')
        ->and($output)->toContain('Done')
        ->and($output)->not->toContain('Invalid option specified');
});

it('writes each option assignment into the preamble exactly once', function () {
    $fixture = $this->fixturePath.'/dedupe-preamble.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=main
# @option release-name=latest

# @task on:local
deploy() {
    echo "b=$BRANCH r=$RELEASE_NAME"
}
BASH);

    try {
        Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--branch' => 'develop',
            '--release-name' => 'v42',
        ]);

        $output = Artisan::output();

        expect(substr_count($output, "BRANCH='develop'"))->toBe(1)
            ->and(substr_count($output, "RELEASE_NAME='v42'"))->toBe(1);
    } finally {
        @unlink($fixture);
    }
});

it('forwards declared options as uppercase env vars', function () {
    $fixture = $this->fixturePath.'/declared-options.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=main

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    try {
        $exitCode = Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--branch' => 'develop',
        ]);

        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain("BRANCH='develop'");
    } finally {
        @unlink($fixture);
    }
});

it('falls back to declared default when option flag is omitted', function () {
    $fixture = $this->fixturePath.'/declared-options-default.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=main

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    try {
        $exitCode = Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
        ]);

        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain("BRANCH='main'");
    } finally {
        @unlink($fixture);
    }
});

it('rejects undeclared flags', function () {
    $fixture = $this->fixturePath.'/no-options.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @task on:local
deploy() {
    echo "hi"
}
BASH);

    try {
        Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--branch' => 'develop',
        ]);

        test()->fail('Expected an exception for undeclared --branch flag.');
    } catch (ExceptionInterface $e) {
        expect($e->getMessage())->toContain('--branch');
    } finally {
        @unlink($fixture);
    }
});

it('evaluates the preamble locally once so dynamic values stay stable across tasks in a macro', function () {
    $fixture = $this->fixturePath.'/zero-downtime.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

NEW_RELEASE_NAME=$(date "+%Y%m%d-%H%M%S%N")
NEW_RELEASE_DIR="app/${NEW_RELEASE_NAME}"

# @macro release test_start test_end

# @task on:local
test_start() {
    echo "${NEW_RELEASE_DIR}"
}

# @task on:local
test_end() {
    echo "${NEW_RELEASE_DIR}"
}
BASH);

    try {
        Artisan::call('run', [
            'task' => 'release',
            '--pretend' => true,
            '--conf' => $fixture,
        ]);

        $output = Artisan::output();

        preg_match_all("/NEW_RELEASE_NAME='([^']+)'/", $output, $matches);

        expect($matches[1])->toHaveCount(2)
            ->and($matches[1][0])->toBe($matches[1][1])
            ->and($output)->toContain("NEW_RELEASE_DIR='app/{$matches[1][0]}'");
    } finally {
        @unlink($fixture);
    }
});

it('exposes dashed option names as snake_case uppercase env vars', function () {
    $fixture = $this->fixturePath.'/dashed-options.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option release-name=latest

# @task on:local
deploy() {
    echo "release=$RELEASE_NAME"
}
BASH);

    try {
        $exitCode = Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--release-name' => 'v42',
        ]);

        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain("RELEASE_NAME='v42'");
    } finally {
        @unlink($fixture);
    }
});

it('omits preamble assignment when option has no default and flag is not passed', function () {
    $fixture = $this->fixturePath.'/optional-no-default.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option tag

# @task on:local
deploy() {
    echo "tag=${TAG:-none}"
}
BASH);

    try {
        $exitCode = Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
        ]);

        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->not->toContain('TAG=');
    } finally {
        @unlink($fixture);
    }
});

it('exposes boolean @option flags as 1 when passed', function () {
    $fixture = $this->fixturePath.'/bool-flag.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option staging

# @task on:local
deploy() {
    echo "staging=$STAGING"
}
BASH);

    try {
        Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--staging' => true,
        ]);

        $output = Artisan::output();

        expect($output)->toContain("STAGING='1'");
    } finally {
        @unlink($fixture);
    }
});

it('omits boolean @option flag when not passed', function () {
    $fixture = $this->fixturePath.'/bool-flag-omitted.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option staging

# @task on:local
deploy() {
    echo "hi"
}
BASH);

    try {
        Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
        ]);

        $output = Artisan::output();

        expect($output)->not->toContain('STAGING=');
    } finally {
        @unlink($fixture);
    }
});

it('errors when a required @option is not provided', function () {
    $fixture = $this->fixturePath.'/required-option.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    try {
        $exitCode = Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
        ]);

        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('--branch');
    } finally {
        @unlink($fixture);
    }
});

it('accepts a required @option when provided via CLI', function () {
    $fixture = $this->fixturePath.'/required-provided.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    try {
        $exitCode = Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--branch' => 'release/1.0',
        ]);

        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain("BRANCH='release/1.0'");
    } finally {
        @unlink($fixture);
    }
});

it('reads string @option from env var when no CLI flag passed', function () {
    $fixture = $this->fixturePath.'/env-fallback.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=main

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    putenv('BRANCH=fromenv');

    try {
        Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
        ]);

        $output = Artisan::output();

        expect($output)->toContain("BRANCH='fromenv'");
    } finally {
        putenv('BRANCH');
        @unlink($fixture);
    }
});

it('prefers CLI flag over env var even when the CLI value equals the declared default', function () {
    $fixture = $this->fixturePath.'/cli-equals-default.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=main

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    putenv('BRANCH=fromenv');

    try {
        Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--branch' => 'main',
        ]);

        $output = Artisan::output();

        expect($output)->toContain("BRANCH='main'")
            ->and($output)->not->toContain("BRANCH='fromenv'");
    } finally {
        putenv('BRANCH');
        @unlink($fixture);
    }
});

it('prefers CLI flag over env var for string @option', function () {
    $fixture = $this->fixturePath.'/env-cli-precedence.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=main

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    putenv('BRANCH=fromenv');

    try {
        Artisan::call('run', [
            'task' => 'deploy',
            '--pretend' => true,
            '--conf' => $fixture,
            '--branch' => 'fromcli',
        ]);

        $output = Artisan::output();

        expect($output)->toContain("BRANCH='fromcli'");
    } finally {
        putenv('BRANCH');
        @unlink($fixture);
    }
});

it('accepts declared flags via ArgvInput regardless of position', function () {
    $fixture = $this->fixturePath.'/argv-order.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @option branch=main

# @task on:local
deploy() {
    echo "branch=$BRANCH"
}
BASH);

    $binary = base_path('scotty');

    try {
        $process = new Process([
            PHP_BINARY,
            $binary,
            'run',
            'deploy',
            '--branch=develop',
            '--conf='.$fixture,
            '--pretend',
        ]);
        $process->run();

        expect($process->getOutput())->toContain("BRANCH='develop'");
    } finally {
        @unlink($fixture);
    }
});

it('shows error when no file found', function () {
    $tempDir = sys_get_temp_dir().'/scotty-run-test-'.uniqid();
    mkdir($tempDir);

    $originalDir = getcwd();
    chdir($tempDir);

    $exitCode = Artisan::call('run', ['task' => 'deploy']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('No Scotty file found');

    chdir($originalDir);
    rmdir($tempDir);
});
