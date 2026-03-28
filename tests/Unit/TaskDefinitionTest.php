<?php

use App\Parsing\TaskDefinition;

it('converts camelCase to humanized name', function () {
    $task = new TaskDefinition(name: 'startDeployment', script: '');

    expect($task->displayName())->toBe('Start deployment');
});

it('converts multi-word camelCase', function () {
    $task = new TaskDefinition(name: 'cloneRepository', script: '');

    expect($task->displayName())->toBe('Clone repository');
});

it('converts single word', function () {
    $task = new TaskDefinition(name: 'deploy', script: '');

    expect($task->displayName())->toBe('Deploy');
});

it('converts snake_case', function () {
    $task = new TaskDefinition(name: 'clear_cache', script: '');

    expect($task->displayName())->toBe('Clear cache');
});

it('converts kebab-case', function () {
    $task = new TaskDefinition(name: 'deploy-code', script: '');

    expect($task->displayName())->toBe('Deploy code');
});

it('handles consecutive uppercase letters', function () {
    $task = new TaskDefinition(name: 'deployOnlySSH', script: '');

    expect($task->displayName())->toBe('Deploy only ssh');
});

it('displayName does not include emoji', function () {
    $task = new TaskDefinition(name: 'startDeployment', script: '', emoji: '🏃');

    expect($task->displayName())->toBe('Start deployment');
});

it('displayNameWithEmoji prepends emoji when set', function () {
    $task = new TaskDefinition(name: 'startDeployment', script: '', emoji: '🏃');

    expect($task->displayNameWithEmoji())->toBe('🏃  Start deployment');
});

it('displayNameWithEmoji returns plain name when no emoji', function () {
    $task = new TaskDefinition(name: 'deploy', script: '', emoji: null);

    expect($task->displayNameWithEmoji())->toBe('Deploy');
});
