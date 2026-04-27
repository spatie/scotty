<?php

use App\Parsing\HookDefinition;
use App\Parsing\HookType;
use App\Parsing\MacroDefinition;
use App\Parsing\ParseResult;
use App\Parsing\ServerDefinition;
use App\Parsing\TaskDefinition;

function createParseResult(): ParseResult
{
    return new ParseResult(
        servers: [
            'local' => new ServerDefinition('local', '127.0.0.1'),
            'remote' => new ServerDefinition('remote', 'forge@1.1.1.1'),
        ],
        tasks: [
            'pull' => new TaskDefinition('pull', 'git pull origin main', ['remote']),
            'build' => new TaskDefinition('build', 'npm run build', ['remote']),
            'restart' => new TaskDefinition('restart', 'sudo systemctl restart nginx', ['remote']),
        ],
        macros: [
            'deploy' => new MacroDefinition('deploy', ['pull', 'build']),
            'deploy-and-restart' => new MacroDefinition('deploy-and-restart', ['deploy', 'restart']),
        ],
        hooks: [
            new HookDefinition(HookType::Before, 'echo "starting"'),
            new HookDefinition(HookType::After, 'echo "done"'),
            new HookDefinition(HookType::Success, 'echo "success"'),
        ],
    );
}

it('returns a task by name', function () {
    $result = createParseResult();

    expect($result->getTask('pull'))->not->toBeNull()
        ->and($result->getTask('pull')->name)->toBe('pull');
});

it('returns null for unknown task', function () {
    $result = createParseResult();

    expect($result->getTask('nonexistent'))->toBeNull();
});

it('returns a macro by name', function () {
    $result = createParseResult();

    expect($result->getMacro('deploy'))->not->toBeNull()
        ->and($result->getMacro('deploy')->name)->toBe('deploy');
});

it('returns null for unknown macro', function () {
    $result = createParseResult();

    expect($result->getMacro('nonexistent'))->toBeNull();
});

it('returns a server by name', function () {
    $result = createParseResult();

    expect($result->getServer('remote'))->not->toBeNull()
        ->and($result->getServer('remote')->hosts)->toBe(['forge@1.1.1.1']);
});

it('returns null for unknown server', function () {
    $result = createParseResult();

    expect($result->getServer('nonexistent'))->toBeNull();
});

it('resolves tasks for a macro name', function () {
    $result = createParseResult();

    $tasks = $result->resolveTasksForTarget('deploy');

    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]->name)->toBe('pull')
        ->and($tasks[1]->name)->toBe('build');
});

it('resolves tasks for a macro name including other macros', function () {
    $result = createParseResult();

    $tasks = $result->resolveTasksForTarget('deploy-and-restart');

    expect($tasks)->toHaveCount(3)
        ->and($tasks[0]->name)->toBe('pull')
        ->and($tasks[1]->name)->toBe('build')
        ->and($tasks[2]->name)->toBe('restart');
});

it('resolves tasks for a single task name', function () {
    $result = createParseResult();

    $tasks = $result->resolveTasksForTarget('restart');

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]->name)->toBe('restart');
});

it('resolves tasks for unknown name to empty array', function () {
    $result = createParseResult();

    expect($result->resolveTasksForTarget('nonexistent'))->toBeEmpty();
});

it('throws when a macro references an unknown task or macro', function () {
    $result = new ParseResult(
        tasks: [
            'pull' => new TaskDefinition('pull', 'echo pull', ['local']),
        ],
        macros: [
            'deploy' => new MacroDefinition('deploy', ['pull', 'pull-typo']),
        ],
    );

    $result->resolveTasksForTarget('deploy');
})->throws(RuntimeException::class, 'Macro "deploy" references unknown target "pull-typo".');

it('detects cyclic macros', function () {
    $result = new ParseResult(
        tasks: [
            'pull' => new TaskDefinition('pull', 'echo pull', ['local']),
        ],
        macros: [
            'a' => new MacroDefinition('a', ['pull', 'b']),
            'b' => new MacroDefinition('b', ['a']),
        ],
    );

    $result->resolveTasksForTarget('a');
})->throws(RuntimeException::class, 'forms a cycle: a -> b -> a');

it('filters hooks by type', function () {
    $result = createParseResult();

    expect($result->getHooks(HookType::Before))->toHaveCount(1)
        ->and($result->getHooks(HookType::Success))->toHaveCount(1)
        ->and($result->getHooks(HookType::Error))->toBeEmpty();
});

it('supports servers with multiple hosts', function () {
    $result = new ParseResult(
        servers: [
            'web' => new ServerDefinition('web', ['forge@1.1.1.1', 'forge@2.2.2.2']),
            'local' => new ServerDefinition('local', '127.0.0.1'),
        ],
    );

    expect($result->getServer('web')->hosts)->toBe(['forge@1.1.1.1', 'forge@2.2.2.2'])
        ->and($result->getServer('local')->hosts)->toBe(['127.0.0.1'])
        ->and($result->getServer('local')->isLocal())->toBeTrue()
        ->and($result->getServer('web')->isLocal())->toBeFalse();
});

it('returns available targets with tasks and macros', function () {
    $result = createParseResult();

    $targets = $result->availableTargets();

    expect($targets)->toHaveKey('tasks')
        ->and($targets)->toHaveKey('macros')
        ->and($targets['tasks'])->toBe(['pull', 'build', 'restart'])
        ->and($targets['macros'])->toBe(['deploy', 'deploy-and-restart']);
});
