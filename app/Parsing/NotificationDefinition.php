<?php

namespace App\Parsing;

final readonly class NotificationDefinition
{
    public function __construct(
        public string $channel,
        public string $webhookUrl,
    ) {}
}
