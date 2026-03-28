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

    /** @return array<string, ServerDefinition> */
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

    /** @return array<string, MacroDefinition> */
    protected function parseMacros(string $content): array
    {
        $macros = [];

        preg_match_all('/^#\s*@macro\s+(\w+)\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $tasks = preg_split('/\s+/', trim($match[2]));

            $macros[$name] = new MacroDefinition($name, $tasks);
        }

        return $macros;
    }

    /** @return array<string, TaskDefinition> */
    protected function parseTasks(string $content): array
    {
        $tasks = [];

        $pattern = '/^#\s*@task\s+(.+)$\n(\w+)\(\)\s*\{/m';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $options = $match[1][0];
            $name = $match[2][0];
            $bodyStart = $match[0][1] + strlen($match[0][0]);

            $script = $this->extractFunctionBody($content, $bodyStart);
            $servers = $this->parseTaskServers($options);
            $isParallel = str_contains($options, 'parallel');
            $confirmMessage = $this->parseTaskConfirm($options);

            $tasks[$name] = new TaskDefinition(
                name: $name,
                script: $this->dedent($script),
                servers: $servers,
                parallel: $isParallel,
                confirm: $confirmMessage,
            );
        }

        return $tasks;
    }

    /** @return array<HookDefinition> */
    protected function parseHooks(string $content): array
    {
        $hooks = [];

        foreach (HookType::cases() as $hookType) {
            $pattern = '/^#\s*@'.$hookType->value.'\s*$\n(\w+)\(\)\s*\{/m';

            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $bodyStart = $match[0][1] + strlen($match[0][0]);
                    $script = $this->extractFunctionBody($content, $bodyStart);

                    $hooks[] = new HookDefinition(
                        type: $hookType,
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

        foreach (explode("\n", $content) as $line) {
            $trimmed = trim($line);

            if (preg_match('/^\w+\(\)\s*\{/', $trimmed)) {
                break;
            }

            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '#!/')) {
                continue;
            }

            if (preg_match('/^[A-Z_][A-Z0-9_]*=/', $trimmed) || preg_match('/^\w+\(\)\s*\{/', $trimmed)) {
                $lines[] = $line;
            }
        }

        $helperFunctions = $this->extractHelperFunctions($content);

        foreach ($cliData as $key => $value) {
            $lines[] = strtoupper($key).'='.escapeshellarg($value);
        }

        $preamble = implode("\n", $lines);

        if ($helperFunctions !== '') {
            $preamble .= "\n".$helperFunctions;
        }

        return $preamble;
    }

    protected function extractHelperFunctions(string $content): string
    {
        $functions = [];

        $pattern = '/^(\w+)\(\)\s*\{/m';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $functionName = $match[1][0];
            $offset = $match[0][1];

            $preceding = substr($content, max(0, $offset - 200), min(200, $offset));

            if (preg_match('/#\s*@(task|before|after|success|error|finished)\b[^\n]*\n\s*$/', $preceding)) {
                continue;
            }

            $bodyStart = $offset + strlen($match[0][0]);
            $body = $this->extractFunctionBody($content, $bodyStart);

            $functions[] = "{$functionName}() {\n{$body}\n}";
        }

        return implode("\n\n", $functions);
    }

    protected function extractFunctionBody(string $content, int $startOffset): string
    {
        $depth = 1;
        $position = $startOffset;
        $length = strlen($content);
        $inSingleQuote = false;
        $inDoubleQuote = false;

        while ($position < $length && $depth > 0) {
            $char = $content[$position];

            if ($char === '\\' && ($inSingleQuote || $inDoubleQuote)) {
                $position += 2;

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
                $position++;
            }
        }

        return substr($content, $startOffset, $position - $startOffset);
    }

    /** @return array<string> */
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

        $dedented = array_map(function (string $line) use ($minIndent): string {
            if (trim($line) === '') {
                return '';
            }

            return substr($line, $minIndent);
        }, $lines);

        return trim(implode("\n", $dedented));
    }
}
