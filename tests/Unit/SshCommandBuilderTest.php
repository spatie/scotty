<?php

use App\Execution\SshCommandBuilder;
use App\Parsing\ServerDefinition;

beforeEach(function () {
    $this->builder = new SshCommandBuilder;
});

it('creates server definition with string host', function () {
    $server = new ServerDefinition('web', 'forge@1.1.1.1');

    expect($server->name)->toBe('web')
        ->and($server->hosts)->toBe(['forge@1.1.1.1']);
});

it('creates server definition with array of hosts', function () {
    $server = new ServerDefinition('web', ['forge@1.1.1.1', 'forge@2.2.2.2']);

    expect($server->name)->toBe('web')
        ->and($server->hosts)->toBe(['forge@1.1.1.1', 'forge@2.2.2.2']);
});

it('detects single local host as local', function () {
    $server = new ServerDefinition('local', '127.0.0.1');

    expect($server->isLocal())->toBeTrue();
});

it('detects multi-host server as not local', function () {
    $server = new ServerDefinition('all', ['127.0.0.1', 'forge@1.1.1.1']);

    expect($server->isLocal())->toBeFalse();
});

it('detects 127.0.0.1 as local', function () {
    expect(ServerDefinition::isLocalHost('127.0.0.1'))->toBeTrue();
});

it('detects localhost as local', function () {
    expect(ServerDefinition::isLocalHost('localhost'))->toBeTrue();
});

it('detects local as local', function () {
    expect(ServerDefinition::isLocalHost('local'))->toBeTrue();
});

it('detects remote host as not local', function () {
    expect(ServerDefinition::isLocalHost('forge@1.1.1.1'))->toBeFalse()
        ->and(ServerDefinition::isLocalHost('example.com'))->toBeFalse()
        ->and(ServerDefinition::isLocalHost('192.168.1.1'))->toBeFalse();
});

it('builds command for local host returning script directly', function () {
    $command = $this->builder->buildCommand('127.0.0.1', 'echo "hello"');

    expect($command)->toBe('echo "hello"');
});

it('builds command for remote host with ssh heredoc', function () {
    $command = $this->builder->buildCommand('forge@1.1.1.1', 'echo "hello"');

    expect($command)->toContain('ssh forge@1.1.1.1')
        ->and($command)->toContain('EOF-SCOTTY')
        ->and($command)->toContain('echo "hello"');
});

it('includes environment variable exports in remote command', function () {
    $command = $this->builder->buildCommand('forge@1.1.1.1', 'echo "hello"', [
        'APP_ENV' => 'production',
        'BRANCH' => 'main',
    ]);

    expect($command)->toContain('export APP_ENV="production"')
        ->and($command)->toContain('export BRANCH="main"');
});

it('normalises lowercase and dashed env keys to uppercase snake_case, deduping variants', function () {
    $command = $this->builder->buildCommand('forge@1.1.1.1', 'echo "hello"', [
        'branch' => 'develop',
        'Branch' => 'develop',
        'release-name' => 'v42',
        'release_name' => 'v42',
        'releaseName' => 'v42',
    ]);

    expect(substr_count($command, 'export BRANCH="develop"'))->toBe(1)
        ->and(substr_count($command, 'export RELEASE_NAME="v42"'))->toBe(1)
        ->and($command)->not->toContain('export branch=')
        ->and($command)->not->toContain('export release-name=');
});

it('includes set -e in remote command', function () {
    $command = $this->builder->buildCommand('forge@1.1.1.1', 'echo "hello"');

    expect($command)->toContain('set -e');
});

it('passes a custom port to ssh when host uses host:port syntax', function () {
    $command = $this->builder->buildCommand('forge@1.1.1.1:2222', 'echo "hello"');

    expect($command)->toContain('ssh -p 2222 forge@1.1.1.1');
});

it('passes a custom port for hostnames without a user', function () {
    $command = $this->builder->buildCommand('example.com:2222', 'echo "hello"');

    expect($command)->toContain('ssh -p 2222 example.com');
});

it('does not add a port flag when none is specified', function () {
    $command = $this->builder->buildCommand('forge@1.1.1.1', 'echo "hello"');

    expect($command)->toContain('ssh forge@1.1.1.1')
        ->and($command)->not->toContain('-p ');
});

it('does not treat port-like syntax on a local host as a port', function () {
    $command = $this->builder->buildCommand('127.0.0.1', 'echo "hello"');

    expect($command)->toBe('echo "hello"');
});
