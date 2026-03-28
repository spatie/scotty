<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->fixturePath = realpath(__DIR__.'/../fixtures');
});

it('lists tasks from a bash file', function () {
    $exitCode = Artisan::call('tasks', ['--conf' => $this->fixturePath.'/complete.sh']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('pull')
        ->and($output)->toContain('migrate')
        ->and($output)->toContain('clearCache')
        ->and($output)->toContain('deployStagingParallel');
});

it('lists macros from a bash file', function () {
    $exitCode = Artisan::call('tasks', ['--conf' => $this->fixturePath.'/complete.sh']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('deploy')
        ->and($output)->toContain('fullDeploy');
});

it('shows error when no file found', function () {
    $tempDir = sys_get_temp_dir().'/scotty-test-'.uniqid();
    mkdir($tempDir);

    $originalDir = getcwd();
    chdir($tempDir);

    $exitCode = Artisan::call('tasks');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('No Scotty file found');

    chdir($originalDir);
    rmdir($tempDir);
});

it('works with --conf option pointing to specific file', function () {
    $exitCode = Artisan::call('tasks', ['--conf' => $this->fixturePath.'/complete.sh']);

    expect($exitCode)->toBe(0);
});
