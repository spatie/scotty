<?php

namespace App\Execution;

use RuntimeException;
use Symfony\Component\Process\Process;

class PreambleEvaluator
{
    protected const VARS_BEGIN_MARKER = '__SCOTTY_PREAMBLE_VARS_BEGIN__';

    protected const VARS_END_MARKER = '__SCOTTY_PREAMBLE_VARS_END__';

    /** @param array<string, string> $env */
    public function evaluate(string $preamble, array $env = []): EvaluatedPreamble
    {
        $variableNames = $this->extractVariableNames($preamble);

        if ($variableNames === []) {
            return new EvaluatedPreamble([], $preamble);
        }

        $values = $this->captureVariableValues($preamble, $variableNames, $env);
        $remaining = $this->stripVariableAssignments($preamble);

        return new EvaluatedPreamble($values, $remaining);
    }

    /** @return array<string> */
    protected function extractVariableNames(string $preamble): array
    {
        preg_match_all('/^\s*([A-Z_][A-Z0-9_]*)=/m', $preamble, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<string>  $variableNames
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    protected function captureVariableValues(string $preamble, array $variableNames, array $env): array
    {
        $dumpLines = '';

        foreach ($variableNames as $name) {
            $dumpLines .= "printf '%s=%s\\n' ".escapeshellarg($name)." \"\${{$name}}\"\n";
        }

        $script = "set -e\n"
            .$preamble."\n"
            .'echo '.escapeshellarg(self::VARS_BEGIN_MARKER)."\n"
            .$dumpLines
            .'echo '.escapeshellarg(self::VARS_END_MARKER)."\n";

        $process = new Process(['bash', '-c', $script]);
        $process->setEnv($env);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException("Failed to evaluate preamble locally: {$message}");
        }

        return $this->parseVariableDump($process->getOutput(), $variableNames);
    }

    /**
     * @param  array<string>  $variableNames
     * @return array<string, string>
     */
    protected function parseVariableDump(string $output, array $variableNames): array
    {
        $startPosition = strpos($output, self::VARS_BEGIN_MARKER);
        $endPosition = strpos($output, self::VARS_END_MARKER);

        if ($startPosition === false || $endPosition === false || $endPosition < $startPosition) {
            throw new RuntimeException('Preamble output is missing the variable markers.');
        }

        $section = substr(
            $output,
            $startPosition + strlen(self::VARS_BEGIN_MARKER),
            $endPosition - $startPosition - strlen(self::VARS_BEGIN_MARKER),
        );

        $values = [];

        foreach (explode("\n", $section) as $line) {
            if (! preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $match)) {
                continue;
            }

            if (! in_array($match[1], $variableNames, true)) {
                continue;
            }

            $values[$match[1]] = $match[2];
        }

        return $values;
    }

    protected function stripVariableAssignments(string $preamble): string
    {
        $lines = explode("\n", $preamble);
        $kept = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*[A-Z_][A-Z0-9_]*=/', $line)) {
                continue;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }
}
