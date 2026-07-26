<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheRedis\RedisCache;
use Elavora\Api\Extension\Redis\NativeRedisConnectionFactory;
use Elavora\Api\Extension\Redis\RedisConfig;
use PHPUnit\Framework\TestCase;

final class RedisCacheIntegrationTest extends TestCase
{
    public function testAppliesDefaultExplicitAndPersistentTtlAgainstRedis(): void
    {
        if (getenv('REDIS_INTEGRATION') !== '1') {
            self::markTestSkipped('Defina REDIS_INTEGRATION=1 para executar a integracao Redis.');
        }

        $host = getenv('REDIS_HOST') ?: 'redis';
        $portValue = getenv('REDIS_PORT');
        $port = $portValue === false ? 6379 : (int) $portValue;
        $config = new RedisConfig(host: $host, port: $port, timeout: 2.0, database: 3);
        $client = (new NativeRedisConnectionFactory())->connect($config);
        $prefix = 'integration:cache:' . bin2hex(random_bytes(6)) . ':';
        $cache = new RedisCache($client, $prefix, defaultTtlSeconds: 60);
        $persistentCache = new RedisCache($client, $prefix);

        $native = new Redis();
        self::assertTrue($native->connect($host, $port, 2.0));
        self::assertTrue($native->select(3));

        try {
            $cache->set('default', 'value');
            $cache->set('explicit', 'value', 10);
            $persistentCache->set('persistent', 'value');

            self::assertGreaterThan(0, $native->ttl($prefix . 'default'));
            self::assertLessThanOrEqual(60, $native->ttl($prefix . 'default'));
            self::assertGreaterThan(0, $native->ttl($prefix . 'explicit'));
            self::assertLessThanOrEqual(10, $native->ttl($prefix . 'explicit'));
            self::assertSame(-1, $native->ttl($prefix . 'persistent'));
        } finally {
            $native->del([
                $prefix . 'default',
                $prefix . 'explicit',
                $prefix . 'persistent',
            ]);
            $native->close();
        }
    }
}
