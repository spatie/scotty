<?php

use App\Ssh\SshConfigFile;

it('parses a simple host entry', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
Host myserver
    HostName 192.168.1.100
    User deploy
SSH);

    expect($config->findConfiguredHost('myserver'))->toBe('myserver');
});

it('parses host with hostname for lookup', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
Host production
    HostName 10.0.0.5
    User forge
SSH);

    expect($config->findConfiguredHost('10.0.0.5'))->toBe('production');
});

it('parses key=value format', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
Host myserver
    HostName=192.168.1.100
    User=deploy
SSH);

    expect($config->findConfiguredHost('myserver'))->toBe('myserver');
});

it('skips comments and blank lines', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
# This is a comment

Host myserver
    # Another comment
    HostName 192.168.1.100

    User deploy
SSH);

    expect($config->findConfiguredHost('myserver'))->toBe('myserver');
});

it('returns matching host from findConfiguredHost', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
Host server-a
    HostName 10.0.0.1
    User deploy

Host server-b
    HostName 10.0.0.2
    User forge
SSH);

    expect($config->findConfiguredHost('server-a'))->toBe('server-a')
        ->and($config->findConfiguredHost('server-b'))->toBe('server-b');
});

it('returns null for unknown host', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
Host myserver
    HostName 192.168.1.100
SSH);

    expect($config->findConfiguredHost('unknown'))->toBeNull();
});

it('handles user@host format', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
Host production
    HostName 10.0.0.5
    User forge
SSH);

    expect($config->findConfiguredHost('forge@production'))->toBe('production');
});

it('skips match sections', function () {
    $config = SshConfigFile::parseString(<<<'SSH'
Host myserver
    HostName 192.168.1.100
    User deploy

Match host *.example.com
    User admin
    ForwardAgent yes

Host another
    HostName 10.0.0.1
SSH);

    expect($config->findConfiguredHost('myserver'))->toBe('myserver')
        ->and($config->findConfiguredHost('another'))->toBe('another');
});
