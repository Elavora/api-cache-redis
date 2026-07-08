<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheRedis\RedisCache;
use Elavora\Api\Extension\CacheRedis\RedisCacheExtension;
use Elavora\Api\Extension\Redis\Contracts\RedisClient;
use Elavora\Api\Extension\Redis\Contracts\RedisConnectionFactory;
use Elavora\Api\Extension\Redis\RedisConfig;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;
use PHPUnit\Framework\TestCase;

final class RedisCacheExtensionTest extends TestCase
{
    public function testUsesSharedRedisConnectionFactory(): void
    {
        $factory = new CacheRedisCountingConnectionFactory();
        $application = Application::create();
        $application->container()->instance(RedisConnectionFactory::class, $factory);

        $application->extend(new RedisCacheExtension([
            'host' => 'redis',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'cache:',
        ]));

        self::assertInstanceOf(RedisCache::class, $application->container()->get(CacheStore::class));
        self::assertSame(1, $factory->connections);
        self::assertSame('redis', $factory->lastConfig?->host);
        self::assertSame(0, $factory->lastConfig?->database);
    }
}

final class CacheRedisCountingConnectionFactory implements RedisConnectionFactory
{
    public int $connections = 0;
    public ?RedisConfig $lastConfig = null;

    public function connect(RedisConfig $config): RedisClient
    {
        $this->connections++;
        $this->lastConfig = $config;

        return new CacheRedisFakeClient();
    }
}

final class CacheRedisFakeClient implements RedisClient
{
    public function get(string $key): string|false
    {
        return false;
    }

    public function set(string $key, string $value): bool
    {
        return true;
    }

    public function setex(string $key, int $ttlSeconds, string $value): bool
    {
        return true;
    }

    public function del(string ...$keys): int|false
    {
        return count($keys);
    }

    public function rPush(string $key, string $value): int|false
    {
        return 1;
    }

    public function lPop(string $key): string|false
    {
        return false;
    }
}
