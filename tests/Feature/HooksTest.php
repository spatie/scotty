<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->logPath = sys_get_temp_dir().'/scotty-hooks-'.uniqid().'.log';
    $this->fixture = realpath(__DIR__.'/../fixtures').'/hooks-'.uniqid();

    $this->writeBladeFixture = function (string $content): string {
        $path = "{$this->fixture}.blade.php";

        file_put_contents($path, "@servers(['local' => '127.0.0.1'])\n\n{$content}");

        return $path;
    };

    $this->hookLog = fn (): string => file_exists($this->logPath) ? file_get_contents($this->logPath) : '';
});

afterEach(function () {
    @unlink($this->logPath);
    @unlink("{$this->fixture}.blade.php");
    @unlink("{$this->fixture}.sh");
});

it('passes the task name to blade @before hooks', function () {
    $fixture = ($this->writeBladeFixture)(<<<BLADE
@task('hello', ['on' => 'local'])
echo hello
@endtask

@before
file_put_contents('{$this->logPath}', "before:{\$task}\\n", FILE_APPEND);
@endbefore
BLADE);

    $exitCode = Artisan::call('run', ['task' => 'hello', '--conf' => $fixture]);

    expect($exitCode)->toBe(0)
        ->and(($this->hookLog)())->toBe("before:hello\n");
});

it('runs blade @after for succeeding tasks and @error for failing tasks', function () {
    $fixture = ($this->writeBladeFixture)(<<<BLADE
@story('deploy')
ok
broken
@endstory

@task('ok', ['on' => 'local'])
echo ok
@endtask

@task('broken', ['on' => 'local'])
exit 1
@endtask

@before
file_put_contents('{$this->logPath}', "before:{\$task}\\n", FILE_APPEND);
@endbefore

@after
file_put_contents('{$this->logPath}', "after:{\$task}\\n", FILE_APPEND);
@endafter

@error
file_put_contents('{$this->logPath}', "error:{\$task}\\n", FILE_APPEND);
@enderror
BLADE);

    $exitCode = Artisan::call('run', ['task' => 'deploy', '--conf' => $fixture]);

    expect($exitCode)->toBe(1)
        ->and(($this->hookLog)())->toBe("before:ok\nafter:ok\nbefore:broken\nerror:broken\n");
});

it('passes the exit code to blade @finished hooks and skips @success on failure', function () {
    $fixture = ($this->writeBladeFixture)(<<<BLADE
@task('broken', ['on' => 'local'])
exit 3
@endtask

@success
file_put_contents('{$this->logPath}', "success\\n", FILE_APPEND);
@endsuccess

@finished
file_put_contents('{$this->logPath}', "finished:{\$exitCode}\\n", FILE_APPEND);
@endfinished
BLADE);

    Artisan::call('run', ['task' => 'broken', '--conf' => $fixture]);

    expect(($this->hookLog)())->toBe("finished:3\n");
});

it('runs blade @success and passes exit code 0 to @finished when all tasks succeed', function () {
    $fixture = ($this->writeBladeFixture)(<<<BLADE
@task('hello', ['on' => 'local'])
echo hello
@endtask

@success
file_put_contents('{$this->logPath}', "success\\n", FILE_APPEND);
@endsuccess

@finished
file_put_contents('{$this->logPath}', "finished:{\$exitCode}\\n", FILE_APPEND);
@endfinished
BLADE);

    Artisan::call('run', ['task' => 'hello', '--conf' => $fixture]);

    expect(($this->hookLog)())->toBe("success\nfinished:0\n");
});

it('exposes @setup variables inside blade hooks', function () {
    $fixture = ($this->writeBladeFixture)(<<<BLADE
@setup
\$environment = 'production';
@endsetup

@task('hello', ['on' => 'local'])
echo hello
@endtask

@before
file_put_contents('{$this->logPath}', "{\$task} on {\$environment}\\n", FILE_APPEND);
@endbefore
BLADE);

    Artisan::call('run', ['task' => 'hello', '--conf' => $fixture]);

    expect(($this->hookLog)())->toBe("hello on production\n");
});

it('does not run hooks in pretend mode', function () {
    $fixture = ($this->writeBladeFixture)(<<<BLADE
@task('hello', ['on' => 'local'])
echo hello
@endtask

@before
file_put_contents('{$this->logPath}', "before\\n", FILE_APPEND);
@endbefore

@success
file_put_contents('{$this->logPath}', "success\\n", FILE_APPEND);
@endsuccess

@finished
file_put_contents('{$this->logPath}', "finished\\n", FILE_APPEND);
@endfinished
BLADE);

    Artisan::call('run', ['task' => 'hello', '--pretend' => true, '--conf' => $fixture]);

    expect(($this->hookLog)())->toBe('');
});

it('reports a throwing blade hook without failing the run', function () {
    $fixture = ($this->writeBladeFixture)(<<<'BLADE'
@task('hello', ['on' => 'local'])
echo hello
@endtask

@before
throw new RuntimeException('Slack is down');
@endbefore
BLADE);

    $exitCode = Artisan::call('run', ['task' => 'hello', '--conf' => $fixture]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())
        ->toContain('@before hook failed')
        ->toContain('Slack is down')
        ->toContain('hello');
});

it('still runs bash format hooks as shell scripts', function () {
    $fixture = "{$this->fixture}.sh";

    file_put_contents($fixture, <<<BASH
# @servers local=127.0.0.1

# @task on:local
deploy() {
    echo "deploying"
}

# @before
beforeHook() {
    echo "before" >> {$this->logPath}
}

# @after
afterHook() {
    echo "after" >> {$this->logPath}
}

# @success
successHook() {
    echo "success" >> {$this->logPath}
}

# @finished
finishedHook() {
    echo "finished" >> {$this->logPath}
}
BASH);

    $exitCode = Artisan::call('run', ['task' => 'deploy', '--conf' => $fixture]);

    expect($exitCode)->toBe(0)
        ->and(($this->hookLog)())->toBe("before\nafter\nsuccess\nfinished\n");
});

it('reports a failing bash format hook', function () {
    $fixture = "{$this->fixture}.sh";

    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @task on:local
deploy() {
    echo "deploying"
}

# @after
afterHook() {
    echo "webhook unreachable" >&2
    exit 7
}
BASH);

    $exitCode = Artisan::call('run', ['task' => 'deploy', '--conf' => $fixture]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())
        ->toContain('@after hook failed')
        ->toContain('Exited with code 7')
        ->toContain('webhook unreachable');
});
