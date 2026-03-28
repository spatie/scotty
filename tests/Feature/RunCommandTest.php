<?php

use Illuminate\Support\Facades\Artisan;

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
