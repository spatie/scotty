<?php

use App\Commands\SshCommand;

it('completes the name argument with remote servers only', function () {
    $values = completionValues(SshCommand::class, [
        'scotty', 'ssh', '--path', realpath(__DIR__.'/../fixtures').'/complete.sh',
    ]);

    expect($values)->toContain('production', 'staging')
        ->and($values)->not->toContain('local');
});
