<?php

namespace App\Execution;

class EvaluatedPreamble
{
    /** @param array<string, string> $variables */
    public function __construct(
        public readonly array $variables,
        public readonly string $remainingPreamble,
    ) {}
}
