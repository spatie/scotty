<?php

namespace App\Commands\Concerns;

use App\Parsing\BashParser;
use App\Parsing\BladeParser;
use App\Parsing\ParseResult;
use App\Parsing\ParserInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

trait ResolvesScottyFile
{
    protected const SCOTTY_FILENAMES = [
        'Scotty.sh',
        'scotty.sh',
        'Scotty.blade.php',
        'scotty.blade.php',
        'Envoy.sh',
        'envoy.sh',
        'Envoy.blade.php',
        'envoy.blade.php',
    ];

    /**
     * Resolve the Scotty file from `--path`, `--conf`, or the working directory.
     *
     * During shell completion the command never runs, so `$this->input` is unset.
     * The CompletionInput is passed in instead — it is an InputInterface too.
     */
    protected function resolveFilePath(?InputInterface $input = null): ?string
    {
        $input ??= $this->input;

        foreach (['path', 'conf'] as $option) {
            $value = $input->getOption($option);

            if (is_string($value) && $value !== '') {
                return file_exists($value) ? $value : null;
            }
        }

        foreach (self::SCOTTY_FILENAMES as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveFilePathOrFail(): ?string
    {
        $filePath = $this->resolveFilePath();

        if ($filePath !== null) {
            return $filePath;
        }

        error('No Scotty file found. Checked for:');

        foreach (self::SCOTTY_FILENAMES as $candidate) {
            note("  - {$candidate}");
        }

        note('');
        note('Run `scotty init` to create one.');

        return null;
    }

    /**
     * Suggest values for a single command argument during shell completion.
     * No-ops for any other completion context and never throws — a completion
     * callback that errors would break the shell's tab completion entirely.
     *
     * @param  callable(ParseResult): array<string>  $values
     */
    protected function completeArgument(
        CompletionInput $input,
        CompletionSuggestions $suggestions,
        string $argument,
        callable $values,
    ): void {
        if (! $input->mustSuggestArgumentValuesFor($argument)) {
            return;
        }

        try {
            $filePath = $this->resolveFilePath($input);

            if ($filePath === null) {
                return;
            }

            $suggestions->suggestValues($values($this->resolveParser($filePath)->parse($filePath)));
        } catch (Throwable) {
            // Stay silent: surfacing an error here would break tab completion.
        }
    }

    protected function resolveParser(string $filePath): ParserInterface
    {
        if (str_ends_with(strtolower($filePath), '.sh')) {
            return new BashParser;
        }

        return new BladeParser;
    }
}
