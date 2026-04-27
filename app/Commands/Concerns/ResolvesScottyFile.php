<?php

namespace App\Commands\Concerns;

use App\Parsing\BashParser;
use App\Parsing\BladeParser;
use App\Parsing\ParserInterface;

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

    protected function resolveParser(string $filePath): ParserInterface
    {
        if (str_ends_with(strtolower($filePath), '.sh')) {
            return new BashParser;
        }

        return new BladeParser;
    }
}
