<?php

namespace App\Parsing;

class BashParser implements ParserInterface
{
    public function parse(string $filePath, array $data = []): ParseResult
    {
        $content = file_get_contents($filePath);

        return new ParseResult(
            servers: $this->parseServers($content),
            tasks: $this->parseTasks($content),
            macros: $this->parseMacros($content),
            hooks: $this->parseHooks($content),
            variablePreamble: $this->parseVariables($content, $data),
        );
    }

    protected function parseServers(string $content): array
    {
        $servers = [];

        if (preg_match('/^#\s*@servers\s+(.+)$/m', $content, $match)) {
            preg_match_all('/(\w+)=(\S+)/', $match[1], $pairs, PREG_SET_ORDER);

            foreach ($pairs as $pair) {
                $servers[$pair[1]] = new ServerDefinition($pair[1], $pair[2]);
            }
        }

        return $servers;
    }

    protected function parseMacros(string $content): array
    {
        $macros = [];

        // Single-line format: # @macro deploy task1 task2 task3
        preg_match_all('/^#\s*@macro\s+(\w+)\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $tasks = preg_split('/\s+/', trim($match[2]));
            $macros[$name] = new MacroDefinition($name, $tasks);
        }

        return $macros;
    }

    protected function parseTasks(string $content): array
    {
        $tasks = [];

        // Match: # @task on:server[,server2] [parallel] [confirm="message"]
        // Followed by: functionName() {
        $pattern = '/^#\s*@task\s+(.+)$\n(\w+)\(\)\s*\{/m';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $options = $match[1][0];
            $name = $match[2][0];
            $bodyStart = $match[0][1] + strlen($match[0][0]);

            $script = $this->extractFunctionBody($content, $bodyStart);
            $servers = $this->parseTaskServers($options);
            $parallel = str_contains($options, 'parallel');
            $confirm = $this->parseTaskConfirm($options);

            $tasks[$name] = new TaskDefinition(
                name: $name,
                script: $this->dedent($script),
                servers: $servers,
                parallel: $parallel,
                confirm: $confirm,
            );
        }

        return $tasks;
    }

    protected function parseHooks(string $content): array
    {
        $hooks = [];
        $hookTypes = ['before', 'after', 'success', 'error', 'finished'];

        foreach ($hookTypes as $type) {
            $pattern = '/^#\s*@' . $type . '\s*$\n(\w+)\(\)\s*\{/m';

            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $bodyStart = $match[0][1] + strlen($match[0][0]);
                    $script = $this->extractFunctionBody($content, $bodyStart);

                    $hooks[] = new HookDefinition(
                        type: HookType::from($type),
                        script: $this->dedent($script),
                    );
                }
            }
        }

        return $hooks;
    }

    protected function parseVariables(string $content, array $cliData): string
    {
        $lines = [];

        // Extract top-level variable assignments (before any function)
        foreach (explode("\n", $content) as $line) {
            $trimmed = trim($line);

            // Stop at first function definition
            if (preg_match('/^\w+\(\)\s*\{/', $trimmed)) {
                break;
            }

            // Skip comments, shebang, empty lines, @annotations
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '#!/')) {
                continue;
            }

            // Capture variable assignments and function definitions (like log())
            if (preg_match('/^[A-Z_][A-Z0-9_]*=/', $trimmed) || preg_match('/^\w+\(\)\s*\{/', $trimmed)) {
                $lines[] = $line;
            }
        }

        // Also extract helper functions (like log()) that appear before tasks
        $helperFunctions = $this->extractHelperFunctions($content);

        // Add CLI dynamic options as variables
        foreach ($cliData as $key => $value) {
            $lines[] = strtoupper($key) . '=' . escapeshellarg($value);
        }

        $preamble = implode("\n", $lines);

        if ($helperFunctions) {
            $preamble .= "\n" . $helperFunctions;
        }

        return $preamble;
    }

    protected function extractHelperFunctions(string $content): string
    {
        $functions = [];

        // Find functions that are NOT preceded by @task or @hook annotations
        $pattern = '/^(\w+)\(\)\s*\{/m';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $name = $match[1][0];
            $offset = $match[0][1];

            // Check if preceded by a @task or @hook annotation
            $preceding = substr($content, max(0, $offset - 200), min(200, $offset));
            if (preg_match('/#\s*@(task|before|after|success|error|finished)\b[^\n]*\n\s*$/', $preceding)) {
                continue;
            }

            $bodyStart = $offset + strlen($match[0][0]);
            $body = $this->extractFunctionBody($content, $bodyStart);
            $functions[] = "{$name}() {\n{$body}\n}";
        }

        return implode("\n\n", $functions);
    }

    protected function extractFunctionBody(string $content, int $startOffset): string
    {
        $depth = 1;
        $i = $startOffset;
        $length = strlen($content);
        $inSingleQuote = false;
        $inDoubleQuote = false;

        while ($i < $length && $depth > 0) {
            $char = $content[$i];

            if ($char === '\\' && ($inSingleQuote || $inDoubleQuote)) {
                $i += 2;
                continue;
            }

            if ($char === "'" && ! $inDoubleQuote) {
                $inSingleQuote = ! $inSingleQuote;
            } elseif ($char === '"' && ! $inSingleQuote) {
                $inDoubleQuote = ! $inDoubleQuote;
            } elseif (! $inSingleQuote && ! $inDoubleQuote) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                }
            }

            if ($depth > 0) {
                $i++;
            }
        }

        return substr($content, $startOffset, $i - $startOffset);
    }

    protected function parseTaskServers(string $options): array
    {
        if (preg_match('/on:(\S+)/', $options, $match)) {
            return explode(',', $match[1]);
        }

        return [];
    }

    protected function parseTaskConfirm(string $options): ?string
    {
        if (preg_match('/confirm="([^"]+)"/', $options, $match)) {
            return $match[1];
        }

        return null;
    }

    protected function dedent(string $text): string
    {
        $lines = explode("\n", $text);

        // Find minimum indentation
        $minIndent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            $minIndent = min($minIndent, $indent);
        }

        if ($minIndent === PHP_INT_MAX || $minIndent === 0) {
            return trim($text);
        }

        $dedented = array_map(function ($line) use ($minIndent) {
            if (trim($line) === '') {
                return '';
            }

            return substr($line, $minIndent);
        }, $lines);

        return trim(implode("\n", $dedented));
    }
}
