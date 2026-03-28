<?php

namespace App\Parsing;

class NotificationDefinition
{
    public function __construct(
        public string $channel,
        public string $webhookUrl,
    ) {}
}
