<?php

use App\Commands\DoctorCommand;

function buildDoctorSshCommand(string $host, string $remoteCommand): array
{
    $method = new ReflectionMethod(DoctorCommand::class, 'buildSshCommand');

    return $method->invoke(new DoctorCommand, $host, $remoteCommand);
}

it('builds the ssh command as an argv array, not a shell string', function () {
    $argv = buildDoctorSshCommand('forge@1.1.1.1', 'echo ok');

    expect($argv)->toBeArray()
        ->and($argv)->toBe([
            'ssh', '-o', 'ConnectTimeout=5', '-o', 'BatchMode=yes', 'forge@1.1.1.1', 'echo ok',
        ]);
});

it('keeps a host containing shell metacharacters as a single literal argv element', function () {
    // The exact payload style from a real @servers host injection report: `;` chains a
    // second local command, and ${IFS} stands in for a space so it survives the
    // whitespace-delimited @servers parser while still being shell-meaningful.
    $maliciousHost = 'evil.example;touch${IFS}/tmp/pwned';

    $argv = buildDoctorSshCommand($maliciousHost, 'echo ok');

    expect($argv)->toBe([
        'ssh', '-o', 'ConnectTimeout=5', '-o', 'BatchMode=yes', $maliciousHost, 'echo ok',
    ]);

    // The host must appear as exactly one, unmodified argv element - never merged
    // with any other token into a combined string. That merging is what let it
    // reach a local shell in the first place.
    $matches = array_filter($argv, fn (string $part): bool => str_contains($part, 'evil.example'));

    expect($matches)->toHaveCount(1)
        ->and(array_values($matches)[0])->toBe($maliciousHost);
});

it('keeps the host and remote command as separate argv elements for the tool-check command', function () {
    $maliciousHost = 'evil.example;touch${IFS}/tmp/pwned';
    $remoteCommand = 'php -v; composer --version';

    $argv = buildDoctorSshCommand($maliciousHost, $remoteCommand);

    expect($argv)->toBe([
        'ssh', '-o', 'ConnectTimeout=5', '-o', 'BatchMode=yes', $maliciousHost, $remoteCommand,
    ]);
});
