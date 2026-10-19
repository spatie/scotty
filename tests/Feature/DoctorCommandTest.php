<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->fixturePath = realpath(__DIR__.'/../fixtures');
});

it('does not flag macros that reference other macros as undefined', function () {
    Artisan::call('doctor', [
        '--conf' => $this->fixturePath.'/nested-macros.sh',
    ]);

    $output = Artisan::output();

    expect($output)
        ->toContain('All macro references resolve')
        ->not->toContain('undefined task or macro');
});

it('passes server hosts literally to both SSH checks without accepting options', function (string $host) {
    $directory = sys_get_temp_dir().'/scotty-doctor-'.uniqid();
    mkdir($directory);

    $originalPath = getenv('PATH');
    $host = str_replace('MARKER', "{$directory}/pwned", $host);

    file_put_contents("{$directory}/ssh", <<<'PHP'
#!/usr/bin/env php
<?php
file_put_contents(__DIR__.'/calls.jsonl', json_encode(array_slice($argv, 1))."\n", FILE_APPEND);

echo end($argv) === 'echo ok'
    ? "ok\n"
    : "PHP 8.4.1\nComposer version 2.8.1\nv22.1.0\n10.8.1\ngit version 2.47.1\n";
PHP);
    chmod("{$directory}/ssh", 0755);

    file_put_contents("{$directory}/scotty.sh", "# @servers web={$host}\n\n# @task on:web\ndeploy() {\n    echo deployed\n}\n");

    putenv("PATH={$directory}:{$originalPath}");

    try {
        $exitCode = Artisan::call('doctor', ['--conf' => "{$directory}/scotty.sh"]);
        $output = Artisan::output();
        $calls = array_map(
            fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            file("{$directory}/calls.jsonl", FILE_IGNORE_NEW_LINES),
        );

        expect($exitCode)->toBe(0)
            ->and(file_exists("{$directory}/pwned"))->toBeFalse()
            ->and($calls)->toBe([
                ['-o', 'ConnectTimeout=5', '-o', 'BatchMode=yes', '--', $host, 'echo ok'],
                ['-o', 'ConnectTimeout=5', '-o', 'BatchMode=yes', '--', $host, 'php -v 2>/dev/null | head -1; composer --version 2>/dev/null; node -v 2>/dev/null; npm -v 2>/dev/null; git --version 2>/dev/null'],
            ])
            ->and($output)->toContain('Everything looks good.', 'php 8.4.1', 'composer 2.8.1', 'node 22.1.0', 'npm 10.8.1', 'git 2.47.1');
    } finally {
        putenv("PATH={$originalPath}");

        foreach (glob("{$directory}/*") as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
})->with([
    'normal host' => 'forge@1.1.1.1',
    'shell metacharacters' => 'evil.example;touch${IFS}MARKER;#',
    'SSH option' => '-V',
]);
