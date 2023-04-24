<?php

// src/Message/RedisMessage.php

namespace App\Message;

class RedisMessage
{
    private $channel;
    private $payload;

    public function __construct(string $channel, string $payload)
    {
        $this->channel = $channel;
        $this->payload = $payload;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }
}
