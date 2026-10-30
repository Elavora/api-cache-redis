<?php

declare(strict_types=1);

use Elavora\Api\Extension\Redis\Contracts\RedisClient;
use Elavora\Api\Extension\Redis\Contracts\RedisConnectionFactory;
use Elavora\Api\Extension\Redis\RedisConfig;

/**
 * RedisConnectionFactory falsa: conta connect(), guarda o último RedisConfig e
 * devolve sempre o mesmo FakeRedisClient.
 */
final class FakeRedisConnectionFactory implements RedisConnectionFactory
{
    public int $connections = 0;
    public ?RedisConfig $lastConfig = null;
    public FakeRedisClient $client;

    public function __construct()
    {
        $this->client = new FakeRedisClient();
    }

    public function connect(RedisConfig $config): RedisClient
    {
        $this->connections++;
        $this->lastConfig = $config;

        return $this->client;
    }
}
