<?php

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/scotty-init-test-'.uniqid();
    mkdir($this->tempDir);
    $this->originalDir = getcwd();
    chdir($this->tempDir);

    $this->app['env'] = 'testing';
});

afterEach(function () {
    chdir($this->originalDir);

    $files = glob($this->tempDir.'/*');
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($this->tempDir);
});

it('creates a Scotty.sh file when bash format selected', function () {
    $this->artisan('init')
        ->expectsChoice('Which format?', 'bash', ['bash' => 'Bash (Scotty.sh)', 'blade' => 'Blade (Scotty.blade.php)'])
        ->expectsQuestion('Server host', 'forge@example.com')
        ->assertExitCode(0);

    expect(file_exists($this->tempDir.'/Scotty.sh'))->toBeTrue();

    $content = file_get_contents($this->tempDir.'/Scotty.sh');

    expect($content)->toContain('forge@example.com')
        ->and($content)->toContain('@servers')
        ->and($content)->toContain('@task');
});

it('creates a Scotty.blade.php file when blade format selected', function () {
    $this->artisan('init')
        ->expectsChoice('Which format?', 'blade', ['bash' => 'Bash (Scotty.sh)', 'blade' => 'Blade (Scotty.blade.php)'])
        ->expectsQuestion('Server host', 'forge@example.com')
        ->assertExitCode(0);

    expect(file_exists($this->tempDir.'/Scotty.blade.php'))->toBeTrue();
});

it('fails when file already exists', function () {
    file_put_contents($this->tempDir.'/Scotty.sh', 'existing');

    $this->artisan('init')
        ->expectsChoice('Which format?', 'bash', ['bash' => 'Bash (Scotty.sh)', 'blade' => 'Blade (Scotty.blade.php)'])
        ->assertExitCode(1);
});
