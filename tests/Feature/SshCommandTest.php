<?php

use App\Commands\SshCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

beforeEach(function () {
    $this->fixturePath = realpath(__DIR__.'/../fixtures');
});

it('completes the name argument with remote servers only', function () {
    $command = resolve(SshCommand::class);
    $command->setApplication(new Application);
    $command->mergeApplicationDefinition();

    $originalCwd = getcwd();
    chdir($this->fixturePath);

    try {
        copy('complete.sh', 'Scotty.sh');

        $input = CompletionInput::fromTokens(['scotty', 'ssh'], 2);
        $input->bind($command->getDefinition());

        $suggestions = new CompletionSuggestions;
        $command->complete($input, $suggestions);

        $values = array_map(
            fn ($suggestion) => (string) $suggestion,
            $suggestions->getValueSuggestions(),
        );

        expect($values)->toContain('production', 'staging')
            ->and($values)->not->toContain('local');
    } finally {
        @unlink($this->fixturePath.'/Scotty.sh');
        chdir($originalCwd);
    }
});
