<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->originalHome = getenv('HOME');
    $this->originalShell = getenv('SHELL');
    $this->home = sys_get_temp_dir().'/scotty-completion-'.uniqid();
    mkdir($this->home, 0755, true);
    putenv("HOME={$this->home}");
});

afterEach(function () {
    putenv($this->originalHome === false ? 'HOME' : "HOME={$this->originalHome}");
    putenv($this->originalShell === false ? 'SHELL' : "SHELL={$this->originalShell}");
    exec('rm -rf '.escapeshellarg($this->home));
});

it('dumps the completion script for a shell', function () {
    $exitCode = Artisan::call('completion', ['shell' => 'zsh']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('#compdef');
});

it('errors for an unsupported shell', function () {
    $exitCode = Artisan::call('completion', ['shell' => 'powershell']);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('not supported');
});

it('installs the completion hook into the shell rc file', function () {
    putenv('SHELL=/bin/zsh');

    $exitCode = Artisan::call('completion', ['shell' => 'install']);

    $rc = $this->home.'/.zshrc';

    expect($exitCode)->toBe(0)
        ->and(file_exists($rc))->toBeTrue()
        ->and(file_get_contents($rc))
        ->toContain('# scotty shell completion')
        ->toContain('completion zsh');
});

it('does not duplicate the hook on repeated installs', function () {
    putenv('SHELL=/bin/bash');

    Artisan::call('completion', ['shell' => 'install']);
    Artisan::call('completion', ['shell' => 'install']);

    $rc = $this->home.'/.bashrc';

    expect(substr_count((string) file_get_contents($rc), '# scotty shell completion'))->toBe(1);
});
