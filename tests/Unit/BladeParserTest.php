<?php

use App\Parsing\BladeParser;

beforeEach(function () {
    $this->parser = new BladeParser;
    $this->fixturePath = __DIR__.'/../fixtures';
});

it('parses emoji from blade task options', function () {
    $result = $this->parser->parse($this->fixturePath.'/blade-with-emoji.blade.php');

    expect($result->getTask('startDeployment')->emoji)->toBe('🏃')
        ->and($result->getTask('noEmoji')->emoji)->toBeNull();
});

it('parses tasks from a simple blade file', function () {
    $result = $this->parser->parse($this->fixturePath.'/simple-blade.blade.php');

    expect($result->tasks)->toHaveCount(1)
        ->and($result->getTask('deploy'))->not->toBeNull()
        ->and($result->getTask('deploy')->servers)->toBe(['local']);
});

it('parses servers from a blade file', function () {
    $result = $this->parser->parse($this->fixturePath.'/simple-blade.blade.php');

    expect($result->servers)->toHaveCount(1)
        ->and($result->getServer('local'))->not->toBeNull()
        ->and($result->getServer('local')->hosts)->toBe(['127.0.0.1']);
});

it('parses servers with array hosts from a blade file', function () {
    $result = $this->parser->parse($this->fixturePath.'/blade-with-array-hosts.blade.php');

    expect($result->servers)->toHaveCount(3)
        ->and($result->getServer('local')->hosts)->toBe(['127.0.0.1'])
        ->and($result->getServer('web')->hosts)->toBe(['forge@1.1.1.1', 'forge@2.2.2.2'])
        ->and($result->getServer('cli')->hosts)->toBe(['forge@3.3.3.3']);
});

it('parses tasks referencing array host servers', function () {
    $result = $this->parser->parse($this->fixturePath.'/blade-with-array-hosts.blade.php');

    expect($result->getTask('deploy')->servers)->toBe(['web'])
        ->and($result->getTask('deploy')->parallel)->toBeTrue()
        ->and($result->getTask('restart-workers')->servers)->toBe(['cli']);
});

it('parses macros from blade file with array hosts', function () {
    $result = $this->parser->parse($this->fixturePath.'/blade-with-array-hosts.blade.php');

    expect($result->getMacro('full-deploy'))->not->toBeNull()
        ->and($result->getMacro('full-deploy')->tasks)->toBe(['deploy', 'restart-workers']);
});
