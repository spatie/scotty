<?php

use App\Completion\CompletionInstaller;
use App\Completion\InstallResult;
use Illuminate\Filesystem\Filesystem;

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

it('installs once and reports already on the second call', function () {
    $installer = new CompletionInstaller;

    expect($installer->install('bash'))->toBe(InstallResult::Installed)
        ->and($installer->install('bash'))->toBe(InstallResult::Already);
});

it('auto-installs only once even across runs', function () {
    putenv('SHELL=/bin/zsh');

    $rcFile = (new CompletionInstaller)->autoInstall();

    expect($rcFile)->toBe($this->home.'/.zshrc')
        ->and(file_get_contents($rcFile))->toContain('completion zsh');

    // Sentinel now exists: a fresh instance must not touch the rc file again.
    expect((new CompletionInstaller)->autoInstall())->toBeNull();
});

it('does not auto-install for an unsupported shell', function () {
    putenv('SHELL=/usr/bin/pwsh');

    expect((new CompletionInstaller)->autoInstall())->toBeNull();
});

it('does not auto-install when HOME is empty', function () {
    putenv('HOME=');
    putenv('SHELL=/bin/bash');

    expect((new CompletionInstaller)->autoInstall())->toBeNull();
});
