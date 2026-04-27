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
            variablePreamble: $this->parseVariables($content),
            options: $this->parseOptions($content),
        );
    }

    public function extractDeclaredOptions(string $filePath): array
    {
        return $this->parseOptions(file_get_contents($filePath));
    }

    /** @return array<string, OptionDefinition> */
    protected function parseOptions(string $content): array
    {
        $options = [];

        preg_match_all('/^#\s*@option\s+(\S.*?)\s*$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $option = OptionDefinition::parse($match[1]);
            $options[$option->name] = $option;
        }

        return $options;
    }

    /** @return array<string, ServerDefinition> */
    protected function parseServers(string $content): array
    {
        $servers = [];

        if (! preg_match('/^#\s*@servers\s+(.+)$/m', $content, $match)) {
            return $servers;
        }

        preg_match_all('/(\w+)=(\S+)/', $match[1], $pairs, PREG_SET_ORDER);

        foreach ($pairs as $pair) {
            $servers[$pair[1]] = new ServerDefinition($pair[1], $pair[2]);
        }

        return $servers;
    }

    /** @return array<string, MacroDefinition> */
    protected function parseMacros(string $content): array
    {
        $macros = [];

        $this->parseSingleLineMacros($content, $macros);
        $this->parseMultiLineMacros($content, $macros);

        return $macros;
    }

    /** @param array<string, MacroDefinition> $macros */
    protected function parseSingleLineMacros(string $content, array &$macros): void
    {
        preg_match_all('/^#\s*@macro\s+(\w[\w-]*)\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $tasks = preg_split('/\s+/', trim($match[2]));

            $macros[$name] = new MacroDefinition($name, $tasks);
        }
    }

    /** @param array<string, MacroDefinition> $macros */
    protected function parseMultiLineMacros(string $content, array &$macros): void
    {
        $pattern = '/^#\s*@macro\s+(\w[\w-]*)\s*$\n((?:#\s+\w[\w-]*\s*\n)+)#\s*@endmacro/m';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $taskLines = explode("\n", trim($match[2]));

            $tasks = array_map(fn (string $line): string => trim(ltrim(trim($line), '#')), $taskLines);
            $tasks = array_filter($tasks, fn (string $task) => $task !== '');

            $macros[$name] = new MacroDefinition($name, array_values($tasks));
        }
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

            $tasks[$name] = new TaskDefinition(
                name: $name,
                script: $this->dedent($script),
                servers: $this->parseTaskServers($options),
                parallel: str_contains($options, 'parallel'),
                confirm: $this->parseTaskConfirm($options),
                emoji: $this->parseTaskEmoji($options),
            );
        }

        return $tasks;
    }

    /** @return array<HookDefinition> */
    protected function parseHooks(string $content): array
    {
        $hooks = [];

        foreach (HookType::cases() as $hookType) {
            $pattern = "/^#\s*@{$hookType->value}\s*\$\n(\w+)\(\)\s*\{/m";

            if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches as $match) {
                $bodyStart = $match[0][1] + strlen($match[0][0]);
                $script = $this->extractFunctionBody($content, $bodyStart);

                $hooks[] = new HookDefinition(
                    type: $hookType,
                    script: $this->dedent($script),
                );
            }
        }

        return $hooks;
    }

    protected function parseVariables(string $content): string
    {
        $lines = [];

        foreach (explode("\n", $content) as $line) {
            $trimmed = trim($line);

            if (preg_match('/^\w+\(\)\s*\{/', $trimmed)) {
                break;
            }

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^[A-Z_][A-Z0-9_]*=/', $trimmed)) {
                $lines[] = $line;
            }
        }

        $helperFunctions = $this->extractHelperFunctions($content);

        $preamble = implode("\n", $lines);

        if ($helperFunctions === '') {
            return $preamble;
        }

        return "{$preamble}\n{$helperFunctions}";
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
            $character = $content[$position];

            if ($character === '\\' && ($inSingleQuote || $inDoubleQuote)) {
                $position += 2;

                continue;
            }

            if ($character === "'" && ! $inDoubleQuote) {
                $inSingleQuote = ! $inSingleQuote;
            } elseif ($character === '"' && ! $inSingleQuote) {
                $inDoubleQuote = ! $inDoubleQuote;
            } elseif (! $inSingleQuote && ! $inDoubleQuote) {
                if ($character === '{') {
                    $depth++;
                } elseif ($character === '}') {
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
        if (! preg_match('/on:(\S+)/', $options, $match)) {
            return [];
        }

        return explode(',', $match[1]);
    }

    protected function parseTaskConfirm(string $options): ?string
    {
        if (! preg_match('/confirm="([^"]+)"/', $options, $match)) {
            return null;
        }

        return $match[1];
    }

    protected function parseTaskEmoji(string $options): ?string
    {
        if (! preg_match('/emoji:(\S+)/', $options, $match)) {
            return null;
        }

        return $match[1];
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
