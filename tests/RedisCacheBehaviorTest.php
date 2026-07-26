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

final class RedisCacheBehaviorTest extends TestCase
{
    public function testUsesDefaultTtlAndLetsExplicitTtlTakePrecedence(): void
    {
        $redis = new RecordingCacheRedisClient();
        $cache = new RedisCache($redis, prefix: 'cache:', defaultTtlSeconds: 60);

        $cache->set('default', 'value');
        self::assertSame(['cache:default', 60], $redis->lastSetex);

        $cache->set('explicit', 'value', 10);
        self::assertSame(['cache:explicit', 10], $redis->lastSetex);
    }

    public function testWritesWithoutExpirationWhenNoTtlExists(): void
    {
        $redis = new RecordingCacheRedisClient();
        $cache = new RedisCache($redis, prefix: 'cache:');

        $cache->set('persistent', 'value');

        self::assertSame('cache:persistent', $redis->lastSet);
        self::assertNull($redis->lastSetex);
    }

    public function testNonPositiveTtlRemovesValue(): void
    {
        $redis = new RecordingCacheRedisClient();
        $cache = new RedisCache($redis, prefix: 'cache:', defaultTtlSeconds: 60);

        $cache->set('zero', 'value', 0);
        self::assertSame('cache:zero', $redis->lastDeleted);

        $cache->set('negative', 'value', -1);
        self::assertSame('cache:negative', $redis->lastDeleted);
    }

    public function testPropagatesSetFailureWithoutPayload(): void
    {
        $redis = new RecordingCacheRedisClient();
        $redis->setResult = false;
        $cache = new RedisCache($redis);
        $payload = 'sensitive-payload';

        try {
            $cache->set('key', $payload);
            self::fail('A escrita deveria falhar.');
        } catch (RuntimeException $exception) {
            self::assertSame('Falha ao gravar o valor no cache Redis.', $exception->getMessage());
            self::assertStringNotContainsString($payload, $exception->getMessage());
        }
    }

    public function testPropagatesSetexFailure(): void
    {
        $redis = new RecordingCacheRedisClient();
        $redis->setexResult = false;
        $cache = new RedisCache($redis);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falha ao gravar o valor com TTL no cache Redis.');

        $cache->set('key', 'value', 30);
    }

    public function testDeleteIsIdempotentForMissingKeyAndPropagatesFailure(): void
    {
        $redis = new RecordingCacheRedisClient();
        $cache = new RedisCache($redis);

        $redis->delResult = 0;
        $cache->delete('missing');
        self::assertSame('missing', $redis->lastDeleted);

        $redis->delResult = 1;
        $cache->delete('existing');
        self::assertSame('existing', $redis->lastDeleted);

        $redis->delResult = false;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falha ao remover o valor do cache Redis.');
        $cache->delete('failure');
    }

    public function testExtensionForwardsConfiguredDefaultTtl(): void
    {
        $redis = new RecordingCacheRedisClient();
        $application = Application::create();
        $application->container()->instance(
            RedisConnectionFactory::class,
            new RecordingCacheRedisConnectionFactory($redis)
        );
        $application->extend(new RedisCacheExtension([
            'host' => 'redis',
            'ttl' => '45',
        ]));

        $cache = $application->container()->get(CacheStore::class);
        self::assertInstanceOf(CacheStore::class, $cache);
        $cache->set('configured', 'value');

        self::assertSame(['configured', 45], $redis->lastSetex);
    }

    public function testExtensionRejectsInvalidDefaultTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O TTL padrao do cache Redis deve ser um inteiro ou null.');

        new RedisCacheExtension(['ttl' => 1.5]);
    }

    public function testExtensionRejectsInvalidPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O prefixo do cache Redis deve ser uma string.');

        new RedisCacheExtension(['prefix' => 123]);
    }
}

final class RecordingCacheRedisConnectionFactory implements RedisConnectionFactory
{
    public function __construct(private readonly RedisClient $redis)
    {
    }

    public function connect(RedisConfig $config): RedisClient
    {
        return $this->redis;
    }
}

final class RecordingCacheRedisClient implements RedisClient
{
    public bool $setResult = true;
    public bool $setexResult = true;
    public int|false $delResult = 1;
    public ?string $lastSet = null;

    /** @var array{string, int}|null */
    public ?array $lastSetex = null;

    public ?string $lastDeleted = null;

    public function get(string $key): string|false
    {
        return false;
    }

    public function set(string $key, string $value): bool
    {
        $this->lastSet = $key;

        return $this->setResult;
    }

    public function setex(string $key, int $ttlSeconds, string $value): bool
    {
        $this->lastSetex = [$key, $ttlSeconds];

        return $this->setexResult;
    }

    public function del(string ...$keys): int|false
    {
        $this->lastDeleted = $keys[0] ?? null;

        return $this->delResult;
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
