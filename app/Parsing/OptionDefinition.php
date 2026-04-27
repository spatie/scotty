<?php

namespace App\Parsing;

use InvalidArgumentException;

final readonly class OptionDefinition
{
    public function __construct(
        public string $name,
        public bool $isBoolean,
        public bool $isRequired,
        public ?string $default,
    ) {}

    /**
     * Parse a signature string into an OptionDefinition.
     *
     * Three forms:
     *   "staging"      boolean flag
     *   "branch=main"  optional value with a default
     *   "tag="         required value
     */
    public static function parse(string $signature): self
    {
        $signature = trim($signature);

        if (! preg_match('/^([a-zA-Z][\w-]*)(=(.*))?$/', $signature, $match)) {
            throw new InvalidArgumentException("Invalid @option signature: \"{$signature}\".");
        }

        $name = $match[1];

        if (! isset($match[2])) {
            return new self($name, isBoolean: true, isRequired: false, default: null);
        }

        $value = self::trimQuotes(trim($match[3] ?? ''));

        return new self(
            $name,
            isBoolean: false,
            isRequired: $value === '',
            default: $value === '' ? null : $value,
        );
    }

    protected static function trimQuotes(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];

        if ($first !== '"' && $first !== "'") {
            return $value;
        }

        $last = substr($value, -1);

        if ($first !== $last) {
            return $value;
        }

        return substr($value, 1, -1);
    }
}
