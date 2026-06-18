<?php

namespace App\Commands\Concerns;

use App\Parsing\BashParser;
use App\Parsing\BladeParser;
use App\Parsing\ParseResult;
use App\Parsing\ParserInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
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

    protected function resolveFilePath(): ?string
    {
        $path = $this->option('path');

        if ($path) {
            return file_exists($path) ? $path : null;
        }

        $filename = $this->option('conf');

        if ($filename !== null) {
            return file_exists($filename) ? $filename : null;
        }

        return $this->firstExistingScottyFile();
    }

    /** Return the first Scotty file present in the working directory, if any. */
    protected function firstExistingScottyFile(): ?string
    {
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
        if ($input->getCompletionType() !== CompletionInput::TYPE_ARGUMENT_VALUE) {
            return;
        }

        if ($input->getCompletionName() !== $argument) {
            return;
        }

        $config = $this->parseForCompletion($input);

        if ($config === null) {
            return;
        }

        foreach ($values($config) as $value) {
            $suggestions->suggestValue($value);
        }
    }

    /**
     * Parse the Scotty file for completion, returning null on any failure so
     * completion stays silent rather than surfacing errors into the shell.
     */
    protected function parseForCompletion(CompletionInput $input): ?ParseResult
    {
        try {
            $filePath = $this->resolveFilePathFromCompletion($input);

            if ($filePath === null) {
                return null;
            }

            return $this->resolveParser($filePath)->parse($filePath);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve the Scotty file during shell completion, where command options
     * are read from the CompletionInput rather than the bound input.
     */
    protected function resolveFilePathFromCompletion(CompletionInput $input): ?string
    {
        $path = $input->getOption('path');

        if (is_string($path) && $path !== '') {
            return file_exists($path) ? $path : null;
        }

        $conf = $input->getOption('conf');

        if (is_string($conf) && $conf !== '') {
            return file_exists($conf) ? $conf : null;
        }

        return $this->firstExistingScottyFile();
    }

    protected function resolveParser(string $filePath): ParserInterface
    {
        if (str_ends_with(strtolower($filePath), '.sh')) {
            return new BashParser;
        }

        return new BladeParser;
    }
}
