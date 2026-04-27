<?php

namespace App\Parsing;

interface ParserInterface
{
    public function parse(string $filePath, array $data = []): ParseResult;

    /**
     * Extract @option declarations without running a full parse.
     *
     * @return array<string, OptionDefinition>
     */
    public function extractDeclaredOptions(string $filePath): array;
}
