<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * Run a command's shell completion the way Symfony's `_complete` does and
 * return the suggested values.
 *
 * The default current index is one past the last token, which is what a shell
 * sends when the cursor sits on a fresh, empty word.
 *
 * @param  class-string<Command>  $commandClass
 * @param  array<string>  $tokens
 * @return array<string>
 */
function completionValues(string $commandClass, array $tokens, ?int $currentIndex = null): array
{
    $command = resolve($commandClass);
    $command->setApplication(new Application);
    $command->mergeApplicationDefinition();

    $input = CompletionInput::fromTokens($tokens, $currentIndex ?? count($tokens));
    $input->bind($command->getDefinition());

    $command->complete($input, $suggestions = new CompletionSuggestions);

    return array_map(strval(...), $suggestions->getValueSuggestions());
}

/** Point HOME at a throwaway directory so rc-file writes never touch the real one. */
function sandboxHome(): string
{
    $home = sys_get_temp_dir().'/scotty-test-'.uniqid();

    mkdir($home, 0755, true);
    putenv("HOME={$home}");

    return $home;
}
