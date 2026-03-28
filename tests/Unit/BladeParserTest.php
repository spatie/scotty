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
        ->and($result->getServer('local')->host)->toBe('127.0.0.1');
});
