<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->originalHome = getenv('HOME');
    $this->originalShell = getenv('SHELL');
    $this->home = sandboxHome();
});

afterEach(function () {
    putenv($this->originalHome === false ? 'HOME' : "HOME={$this->originalHome}");
    putenv($this->originalShell === false ? 'SHELL' : "SHELL={$this->originalShell}");
    (new Filesystem)->deleteDirectory($this->home);
});

it('installs the completion hook into the shell rc file', function () {
    putenv('SHELL=/bin/zsh');

    $exitCode = Artisan::call('completion:install');

    $rc = $this->home.'/.zshrc';

    expect($exitCode)->toBe(0)
        ->and(file_get_contents($rc))
        ->toContain('# scotty shell completion')
        ->toContain('completion zsh');
});

it('does not duplicate the hook on repeated installs', function () {
    putenv('SHELL=/bin/bash');

    Artisan::call('completion:install');
    Artisan::call('completion:install');

    $rc = $this->home.'/.bashrc';

    expect(substr_count((string) file_get_contents($rc), '# scotty shell completion'))->toBe(1);
});

it('errors for an unsupported shell', function () {
    $exitCode = Artisan::call('completion:install', ['shell' => 'powershell']);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('Could not detect a supported shell');
});

it('still dumps the raw script through the built-in completion command', function () {
    $exitCode = Artisan::call('completion', ['shell' => 'zsh']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('#compdef');
});
