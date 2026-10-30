<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheRedis\RedisCache;
use Elavora\Api\Extension\CacheRedis\RedisCacheExtension;
use Elavora\Api\Extension\Redis\Contracts\RedisConnectionFactory;
use Elavora\Api\Extension\Redis\RedisConnectionManager;
use Elavora\Api\Extension\Redis\RedisExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Contracts\Extension;
use PHPUnit\Framework\TestCase;

final class RedisCacheExtensionTest extends TestCase
{
    private FakeRedisConnectionFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FakeRedisConnectionFactory();
    }

    /** @param array<string, mixed> $config */
    private function applicationWithFactory(array $config = []): Application
    {
        $application = Application::create();
        $application->container()->instance(RedisConnectionFactory::class, $this->factory);
        $application->extend(new RedisCacheExtension($config));

        return $application;
    }

    public function testImplementsExtension(): void
    {
        self::assertInstanceOf(Extension::class, new RedisCacheExtension());
    }

    public function testConstructorDoesNotThrowWithUnknownAndStringOptions(): void
    {
        $extension = new RedisCacheExtension([
            'host' => 'redis',
            'port' => '6380',
            'timeout' => '1.5',
            'password' => '',
            'database' => '0',
            'prefix' => null,
            'ttl' => '-1',
            'extra' => ['qualquer' => 'coisa'],
        ]);

        self::assertInstanceOf(RedisCacheExtension::class, $extension);
    }

    // Cenários do USO.md

    public function testUsesFactoryRegisteredInContainer(): void
    {
        $application = $this->applicationWithFactory([
            'host' => 'redis',
            'database' => 0,
            'prefix' => 'cache:',
        ]);

        self::assertInstanceOf(RedisCache::class, $application->container()->get(CacheStore::class));
        self::assertSame(1, $this->factory->connections);
        self::assertSame('redis', $this->factory->lastConfig?->host);
        self::assertSame(0, $this->factory->lastConfig?->database);
    }

    public function testRegisterDoesNotConnect(): void
    {
        $this->applicationWithFactory([
            'host' => 'redis',
            'database' => 0,
            'prefix' => 'cache:',
        ]);

        self::assertSame(0, $this->factory->connections);
    }

    public function testResolvedCacheIsSharedAndConnectsOnce(): void
    {
        $application = $this->applicationWithFactory([
            'host' => 'redis',
            'database' => 0,
            'prefix' => 'cache:',
        ]);

        $first = $application->container()->get(CacheStore::class);
        $second = $application->container()->get(CacheStore::class);

        self::assertSame($first, $second);
        self::assertSame(1, $this->factory->connections);
    }

    public function testPrefixAndStringTtlReachRedisCache(): void
    {
        $application = $this->applicationWithFactory(['prefix' => 'p:', 'ttl' => '30']);

        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame([['setex', ['p:k', 30, 's:1:"v";']]], $this->factory->client->calls);
    }

    public function testIntTtlReachesRedisCache(): void
    {
        $application = $this->applicationWithFactory(['ttl' => 60]);

        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame([['setex', ['k', 60, 's:1:"v";']]], $this->factory->client->calls);
    }

    public function testDefaultsWithoutConfig(): void
    {
        $application = $this->applicationWithFactory();

        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame([['set', ['k', 's:1:"v";']]], $this->factory->client->calls);
        self::assertSame('127.0.0.1', $this->factory->lastConfig?->host);
        self::assertSame(6379, $this->factory->lastConfig?->port);
        self::assertSame(0.0, $this->factory->lastConfig?->timeout);
        self::assertNull($this->factory->lastConfig?->password);
        self::assertNull($this->factory->lastConfig?->database);
    }

    public function testConnectionOptionsAreNormalizedAndUnknownKeysIgnored(): void
    {
        $application = $this->applicationWithFactory([
            'host' => 'redis',
            'port' => '6380',
            'password' => '',
            'database' => '2',
            'extra' => 1,
        ]);

        $application->container()->get(CacheStore::class);

        self::assertSame('redis', $this->factory->lastConfig?->host);
        self::assertSame(6380, $this->factory->lastConfig?->port);
        self::assertNull($this->factory->lastConfig?->password);
        self::assertSame(2, $this->factory->lastConfig?->database);
    }

    public function testStringDatabaseZeroBecomesZero(): void
    {
        $application = $this->applicationWithFactory(['database' => '0']);

        $application->container()->get(CacheStore::class);

        self::assertSame(0, $this->factory->lastConfig?->database);
    }

    public function testTimeoutAndPasswordReachConfig(): void
    {
        $application = $this->applicationWithFactory(['timeout' => '1.5', 'password' => 'segredo']);

        $application->container()->get(CacheStore::class);

        self::assertSame(1.5, $this->factory->lastConfig?->timeout);
        self::assertSame('segredo', $this->factory->lastConfig?->password);
    }

    public function testNullPrefixAndNullTtlMeanNoPrefixAndNoExpiration(): void
    {
        $application = $this->applicationWithFactory(['prefix' => null, 'ttl' => null]);

        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame([['set', ['k', 's:1:"v";']]], $this->factory->client->calls);
    }

    public function testZeroTtlFromConfigDeletesInsteadOfWriting(): void
    {
        $application = $this->applicationWithFactory(['prefix' => 'p:', 'ttl' => '0']);

        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame([['del', ['p:k']]], $this->factory->client->calls);
    }

    public function testNegativeTtlFromConfigDeletesInsteadOfWriting(): void
    {
        $application = $this->applicationWithFactory(['ttl' => -10]);

        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame([['del', ['k']]], $this->factory->client->calls);
    }

    public function testCallTtlWinsOverConfigTtl(): void
    {
        $application = $this->applicationWithFactory(['ttl' => '30']);

        $application->container()->get(CacheStore::class)->set('k', 'v', 5);

        self::assertSame([['setex', ['k', 5, 's:1:"v";']]], $this->factory->client->calls);
    }

    public function testEmptyContainerReceivesRedisConnectionManagerWithoutConnecting(): void
    {
        $application = Application::create();
        $application->extend(new RedisCacheExtension(['host' => 'redis']));

        self::assertTrue($application->container()->has(RedisConnectionFactory::class));
        self::assertInstanceOf(
            RedisConnectionManager::class,
            $application->container()->get(RedisConnectionFactory::class),
        );
    }

    public function testUsesFactoryRegisteredByRedisExtension(): void
    {
        $application = Application::create();
        $application->extend(new RedisExtension($this->factory));
        $application->extend(new RedisCacheExtension(['host' => 'redis', 'prefix' => 'x:']));

        self::assertSame(0, $this->factory->connections);

        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame(1, $this->factory->connections);
        self::assertSame('redis', $this->factory->lastConfig?->host);
        self::assertSame([['set', ['x:k', 's:1:"v";']]], $this->factory->client->calls);
    }

    public function testManualRegistrationExampleIsChainable(): void
    {
        $application = Application::create();
        $application->container()->instance(RedisConnectionFactory::class, $this->factory);

        $returned = $application->extend(new RedisCacheExtension([
            'host' => 'redis',
            'prefix' => 'app:',
            'ttl' => 3600,
        ]));

        $cache = $returned->container()->get(CacheStore::class);
        self::assertInstanceOf(RedisCache::class, $cache);

        $cache->set('k', 'v');
        self::assertSame([['setex', ['app:k', 3600, 's:1:"v";']]], $this->factory->client->calls);
    }

    // Bootstrap do framework 2

    public function testEnvironmentExtensionsExample(): void
    {
        $extensions = EnvironmentExtensions::load('/app', [
            'CACHE_DRIVER' => 'redis',
            'REDIS_DATABASE' => '2',
            'CACHE_TTL' => '30',
        ]);

        self::assertCount(1, $extensions);
        self::assertInstanceOf(RedisCacheExtension::class, $extensions[0]);

        $application = Application::create();
        $application->container()->instance(RedisConnectionFactory::class, $this->factory);
        $application->extend($extensions[0]);
        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertSame('redis', $this->factory->lastConfig?->host);
        self::assertSame(6379, $this->factory->lastConfig?->port);
        self::assertNull($this->factory->lastConfig?->password);
        self::assertSame(2, $this->factory->lastConfig?->database);
        self::assertSame([['setex', ['api:cache:k', 30, 's:1:"v";']]], $this->factory->client->calls);
    }

    public function testEnvironmentExtensionsWithoutTtlStoresWithoutExpiration(): void
    {
        $extensions = EnvironmentExtensions::load('/app', ['CACHE_DRIVER' => 'redis']);

        self::assertCount(1, $extensions);

        $application = Application::create();
        $application->container()->instance(RedisConnectionFactory::class, $this->factory);
        $application->extend($extensions[0]);
        $application->container()->get(CacheStore::class)->set('k', 'v');

        self::assertNull($this->factory->lastConfig?->database);
        self::assertSame([['set', ['api:cache:k', 's:1:"v";']]], $this->factory->client->calls);
    }

    // Binding inválido (USO.md)

    public function testInvalidFactoryBindingThrowsLogicException(): void
    {
        $application = Application::create();
        $application->container()->instance(RedisConnectionFactory::class, new stdClass());
        $application->extend(new RedisCacheExtension());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'O binding de Elavora\Api\Extension\Redis\Contracts\RedisConnectionFactory deve resolver uma instancia dessa interface.',
        );

        $application->container()->get(CacheStore::class);
    }
}
