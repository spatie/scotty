<?php

use App\Parsing\BashParser;
use App\Parsing\HookType;

beforeEach(function () {
    $this->parser = new BashParser;
    $this->fixturePath = __DIR__.'/../fixtures';
});

it('parses servers from @servers annotation', function () {
    $result = $this->parser->parse($this->fixturePath.'/complete.sh');

    expect($result->servers)->toHaveCount(3)
        ->and($result->servers['local']->name)->toBe('local')
        ->and($result->servers['local']->host)->toBe('127.0.0.1')
        ->and($result->servers['production']->name)->toBe('production')
        ->and($result->servers['production']->host)->toBe('forge@production.example.com')
        ->and($result->servers['staging']->name)->toBe('staging')
        ->and($result->servers['staging']->host)->toBe('forge@staging.example.com');
});

it('parses macros from @macro annotation', function () {
    $result = $this->parser->parse($this->fixturePath.'/complete.sh');

    expect($result->macros)->toHaveCount(2)
        ->and($result->macros['deploy']->name)->toBe('deploy')
        ->and($result->macros['deploy']->tasks)->toBe(['pull', 'migrate'])
        ->and($result->macros['fullDeploy']->name)->toBe('fullDeploy')
        ->and($result->macros['fullDeploy']->tasks)->toBe(['pull', 'migrate', 'clearCache']);
});

it('parses multi-line macros', function () {
    $fixture = $this->fixturePath.'/multiline-macro.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @macro deploy
#   pullCode
#   runComposer
#   clearCaches
# @endmacro

# @task on:local
pullCode() {
    echo "pulling"
}

# @task on:local
runComposer() {
    echo "composing"
}

# @task on:local
clearCaches() {
    echo "clearing"
}
BASH);

    $result = $this->parser->parse($fixture);

    expect($result->macros)->toHaveCount(1)
        ->and($result->macros['deploy']->tasks)->toBe(['pullCode', 'runComposer', 'clearCaches']);

    @unlink($fixture);
});

it('supports both single-line and multi-line macros together', function () {
    $fixture = $this->fixturePath.'/mixed-macros.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1
# @macro quick taskA taskB

# @macro full
#   taskA
#   taskB
#   taskC
# @endmacro

# @task on:local
taskA() { echo "a"; }

# @task on:local
taskB() { echo "b"; }

# @task on:local
taskC() { echo "c"; }
BASH);

    $result = $this->parser->parse($fixture);

    expect($result->macros)->toHaveCount(2)
        ->and($result->macros['quick']->tasks)->toBe(['taskA', 'taskB'])
        ->and($result->macros['full']->tasks)->toBe(['taskA', 'taskB', 'taskC']);

    @unlink($fixture);
});

it('parses tasks with on:server directive', function () {
    $result = $this->parser->parse($this->fixturePath.'/complete.sh');

    $task = $result->getTask('pull');

    expect($task)->not->toBeNull()
        ->and($task->name)->toBe('pull')
        ->and($task->servers)->toBe(['local'])
        ->and($task->parallel)->toBeFalse()
        ->and($task->confirm)->toBeNull()
        ->and($task->script)->toContain('git pull origin $BRANCH');
});

it('parses tasks with parallel flag', function () {
    $result = $this->parser->parse($this->fixturePath.'/complete.sh');

    $task = $result->getTask('deployStagingParallel');

    expect($task)->not->toBeNull()
        ->and($task->parallel)->toBeTrue()
        ->and($task->servers)->toBe(['staging']);
});

it('parses tasks with confirm message', function () {
    $fixture = $this->fixturePath.'/confirm.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers remote=forge@1.1.1.1

# @task on:remote confirm="Are you sure you want to seed?"
seed() {
    php artisan db:seed --force
}
BASH);

    $result = $this->parser->parse($fixture);
    $task = $result->getTask('seed');

    expect($task)->not->toBeNull()
        ->and($task->confirm)->toBe('Are you sure you want to seed?')
        ->and($task->servers)->toBe(['remote']);

    @unlink($fixture);
});

it('parses tasks targeting multiple servers', function () {
    $fixture = $this->fixturePath.'/multi_server.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers web-1=10.0.0.1 web-2=10.0.0.2

# @task on:web-1,web-2
restart() {
    sudo systemctl restart nginx
}
BASH);

    $result = $this->parser->parse($fixture);
    $task = $result->getTask('restart');

    expect($task)->not->toBeNull()
        ->and($task->servers)->toBe(['web-1', 'web-2']);

    @unlink($fixture);
});

it('parses lifecycle hooks', function () {
    $result = $this->parser->parse($this->fixturePath.'/complete.sh');

    expect($result->hooks)->toHaveCount(3);

    $beforeHooks = $result->getHooks(HookType::Before);
    $afterHooks = $result->getHooks(HookType::After);
    $errorHooks = $result->getHooks(HookType::Error);

    expect($beforeHooks)->toHaveCount(1)
        ->and($afterHooks)->toHaveCount(1)
        ->and($errorHooks)->toHaveCount(1);

    expect(array_values($beforeHooks)[0]->script)->toContain('Starting deployment');
    expect(array_values($afterHooks)[0]->script)->toContain('Deployment complete');
    expect(array_values($errorHooks)[0]->script)->toContain('Something went wrong');
});

it('parses all lifecycle hook types', function () {
    $fixture = $this->fixturePath.'/all_hooks.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @task on:local
deploy() {
    echo "deploying"
}

# @before
setup() {
    echo "before"
}

# @after
teardown() {
    echo "after"
}

# @success
onSuccess() {
    echo "success"
}

# @error
onError() {
    echo "error"
}

# @finished
onFinished() {
    echo "finished"
}
BASH);

    $result = $this->parser->parse($fixture);

    expect($result->hooks)->toHaveCount(5)
        ->and($result->getHooks(HookType::Before))->toHaveCount(1)
        ->and($result->getHooks(HookType::After))->toHaveCount(1)
        ->and($result->getHooks(HookType::Success))->toHaveCount(1)
        ->and($result->getHooks(HookType::Error))->toHaveCount(1)
        ->and($result->getHooks(HookType::Finished))->toHaveCount(1);

    @unlink($fixture);
});

it('parses top-level variable assignments', function () {
    $result = $this->parser->parse($this->fixturePath.'/complete.sh');

    expect($result->variablePreamble)->toContain('BRANCH="main"')
        ->and($result->variablePreamble)->toContain('APP_DIR="/home/forge/myapp"');
});

it('extracts helper functions into variable preamble', function () {
    $fixture = $this->fixturePath.'/with_helper.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

format_date() {
    date +"%Y-%m-%d"
}

# @task on:local
deploy() {
    echo "deploying at $(format_date)"
}
BASH);

    $result = $this->parser->parse($fixture);

    expect($result->variablePreamble)->toContain('format_date()')
        ->and($result->variablePreamble)->toContain('date +"%Y-%m-%d"');

    @unlink($fixture);
});

it('handles nested braces in function bodies', function () {
    $fixture = $this->fixturePath.'/nested_braces.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @task on:local
nested() {
    if [ "$ENV" = "production" ]; then
        if [ -d "/var/www" ]; then
            echo "exists"
        fi
    fi
}
BASH);

    $result = $this->parser->parse($fixture);
    $task = $result->getTask('nested');

    expect($task)->not->toBeNull()
        ->and($task->script)->toContain('if [ "$ENV" = "production" ]')
        ->and($task->script)->toContain('echo "exists"');

    @unlink($fixture);
});

it('adds cli data to variable preamble', function () {
    $fixture = $this->fixturePath.'/simple.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @task on:local
deploy() {
    echo "deploying $BRANCH"
}
BASH);

    $result = $this->parser->parse($fixture, ['branch' => 'main', 'env' => 'production']);

    expect($result->variablePreamble)->toContain("BRANCH='main'")
        ->and($result->variablePreamble)->toContain("ENV='production'");

    @unlink($fixture);
});

it('parses emoji from task annotation', function () {
    $fixture = $this->fixturePath.'/emoji.sh';
    file_put_contents($fixture, <<<'BASH'
# @servers local=127.0.0.1

# @task on:local emoji:🚀
deploy() {
    echo "deploying"
}

# @task on:local
noEmoji() {
    echo "no emoji"
}
BASH);

    $result = $this->parser->parse($fixture);

    expect($result->getTask('deploy')->emoji)->toBe('🚀')
        ->and($result->getTask('noEmoji')->emoji)->toBeNull();

    @unlink($fixture);
});

it('produces empty parse result for empty file', function () {
    $fixture = $this->fixturePath.'/empty.sh';
    file_put_contents($fixture, '');

    $result = $this->parser->parse($fixture);

    expect($result->servers)->toBeEmpty()
        ->and($result->tasks)->toBeEmpty()
        ->and($result->macros)->toBeEmpty()
        ->and($result->hooks)->toBeEmpty()
        ->and($result->variablePreamble)->toBe('');

    @unlink($fixture);
});
