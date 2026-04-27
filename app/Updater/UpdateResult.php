<?php

namespace App\Updater;

final readonly class UpdateResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $error = null,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
