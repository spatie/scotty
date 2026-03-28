<?php

namespace App\Parsing;

interface ParserInterface
{
    public function parse(string $filePath, array $data = []): ParseResult;
}
