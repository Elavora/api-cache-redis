<?php

declare(strict_types=1);

use Elavora\Api\Extension\CacheRedis\RedisCache;
use Elavora\Api\Framework\Contracts\CacheStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedisCacheTest extends TestCase
{
    private FakeRedisClient $client;

    protected function setUp(): void
    {
        $this->client = new FakeRedisClient();
    }

    public function testImplementsCacheStore(): void
    {
        self::assertInstanceOf(CacheStore::class, new RedisCache($this->client));
    }

    public function testConstructorAcceptsNamedArguments(): void
    {
        $cache = new RedisCache(redis: $this->client, prefix: 'n:', defaultTtlSeconds: 15);
        $cache->set('k', 'v');

        self::assertSame([['setex', ['n:k', 15, 's:1:"v";']]], $this->client->calls);
    }

    // Chaves e prefixo

    public function testSetWritesPrefixedKey(): void
    {
        $cache = new RedisCache($this->client, prefix: 'app:');
        $cache->set('user', 1);

        self::assertSame([['set', ['app:user', serialize(1)]]], $this->client->calls);
    }

    public function testEmptyPrefixUsesKeyAsIs(): void
    {
        $cache = new RedisCache($this->client);
        $cache->set('user', 1);

        self::assertSame([['set', ['user', 'i:1;']]], $this->client->calls);
    }

    public function testInstancesWithDifferentPrefixesDoNotSeeEachOther(): void
    {
        $a = new RedisCache($this->client, prefix: 'a:');
        $b = new RedisCache($this->client, prefix: 'b:');

        $a->set('k', 'from-a');

        self::assertSame('from-a', $a->get('k'));
        self::assertNull($b->get('k'));

        $b->set('k', 'from-b');
        self::assertSame('from-a', $a->get('k'));
        self::assertSame('from-b', $b->get('k'));
    }

    // Formato do valor

    public function testValueIsStoredSerialized(): void
    {
        $cache = new RedisCache($this->client, prefix: 'app:');
        $cache->set('a', ['x' => 1]);

        self::assertSame([['set', ['app:a', 'a:1:{s:1:"x";i:1;}']]], $this->client->calls);
    }

    public function testGetUnserializesValueStoredInClient(): void
    {
        $this->client->store['app:a'] = serialize(['x' => 1]);
        $cache = new RedisCache($this->client, prefix: 'app:');

        self::assertSame(['x' => 1], $cache->get('a'));
    }

    // Leitura

    public function testGetCallsClientWithPrefixedKey(): void
    {
        $cache = new RedisCache($this->client, prefix: 'app:');
        $cache->get('user');

        self::assertSame([['get', ['app:user']]], $this->client->calls);
    }

    public function testMissingKeyReturnsNull(): void
    {
        $cache = new RedisCache($this->client);

        self::assertNull($cache->get('missing'));
    }

    public function testStoredFalseReturnsFalseNotAbsence(): void
    {
        $cache = new RedisCache($this->client);
        $cache->set('flag', false);

        self::assertSame([['set', ['flag', 'b:0;']]], $this->client->calls);
        self::assertFalse($cache->get('flag'));
        self::assertNull($cache->get('missing'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function storedValues(): iterable
    {
        yield 'array' => [['status' => 'ok', 'list' => [1, 2, 3]]];
        yield 'int' => [42];
        yield 'zero' => [0];
        yield 'string' => ['texto'];
        yield 'empty string' => [''];
        yield 'false' => [false];
        yield 'true' => [true];
        yield 'null' => [null];
        yield 'float' => [1.5];
    }

    #[DataProvider('storedValues')]
    public function testRoundTripKeepsTypeAndValue(mixed $value): void
    {
        $cache = new RedisCache($this->client, prefix: 'rt:');
        $cache->set('k', $value);

        self::assertSame(serialize($value), $this->client->store['rt:k']);
        self::assertSame($value, $cache->get('k'));
    }

    public function testRoundTripKeepsObject(): void
    {
        $object = new \stdClass();
        $object->name = 'elavora';

        $cache = new RedisCache($this->client);
        $cache->set('obj', $object);
        $read = $cache->get('obj');

        self::assertInstanceOf(\stdClass::class, $read);
        self::assertEquals($object, $read);
    }

    // TTL

    public function testDefaultTtlUsesSetex(): void
    {
        $cache = new RedisCache($this->client, prefix: 'ttl:', defaultTtlSeconds: 60);
        $cache->set('key', 'stored');

        self::assertSame([['setex', ['ttl:key', 60, 's:6:"stored";']]], $this->client->calls);
    }

    public function testCallTtlZeroDeletesAndDoesNotWrite(): void
    {
        $cache = new RedisCache($this->client, prefix: 'ttl:', defaultTtlSeconds: 60);
        $cache->set('key', 'ignored', 0);

        self::assertSame([['del', ['ttl:key']]], $this->client->calls);
        self::assertSame([], $this->client->writes());
    }

    public function testCallTtlZeroRemovesPreviousValue(): void
    {
        $cache = new RedisCache($this->client, prefix: 'ttl:', defaultTtlSeconds: 60);
        $cache->set('key', 'stored');
        $cache->set('key', 'ignored', 0);

        self::assertNull($cache->get('key'));
    }

    public function testDefaultTtlZeroDeletesAndDoesNotWrite(): void
    {
        $cache = new RedisCache($this->client, defaultTtlSeconds: 0);
        $cache->set('key', 'value');

        self::assertSame([['del', ['key']]], $this->client->calls);
    }

    public function testDefaultTtlNegativeDeletesAndDoesNotWrite(): void
    {
        $cache = new RedisCache($this->client, defaultTtlSeconds: -1);
        $cache->set('key', 'value');

        self::assertSame([['del', ['key']]], $this->client->calls);
    }

    public function testCallTtlWinsOverZeroDefault(): void
    {
        $cache = new RedisCache($this->client, defaultTtlSeconds: 0);
        $cache->set('key', 'value', 30);

        self::assertSame([['setex', ['key', 30, 's:5:"value";']]], $this->client->calls);
    }

    public function testCallTtlWinsOverPositiveDefault(): void
    {
        $cache = new RedisCache($this->client, defaultTtlSeconds: 60);
        $cache->set('key', 'value', 10);

        self::assertSame([['setex', ['key', 10, 's:5:"value";']]], $this->client->calls);
    }

    public function testNullCallTtlFallsBackToDefault(): void
    {
        $cache = new RedisCache($this->client, defaultTtlSeconds: 60);
        $cache->set('key', 'value', null);

        self::assertSame([['setex', ['key', 60, 's:5:"value";']]], $this->client->calls);
    }

    public function testNoTtlAnywhereStoresWithoutExpiration(): void
    {
        $cache = new RedisCache($this->client);
        $cache->set('key', 'value');

        self::assertSame([['set', ['key', 's:5:"value";']]], $this->client->calls);
    }

    public function testNegativeCallTtlDeletes(): void
    {
        $cache = new RedisCache($this->client);
        $cache->set('key', 'value', -5);

        self::assertSame([['del', ['key']]], $this->client->calls);
    }

    public function testPositiveCallTtlWithoutDefaultUsesSetex(): void
    {
        $cache = new RedisCache($this->client);
        $cache->set('key', 'value', 1);

        self::assertSame([['setex', ['key', 1, 's:5:"value";']]], $this->client->calls);
    }

    // Remoção

    public function testDeleteCallsDelWithPrefixedKey(): void
    {
        $cache = new RedisCache($this->client, prefix: 'app:');
        $cache->set('user', 1);
        $this->client->calls = [];

        $cache->delete('user');

        self::assertSame([['del', ['app:user']]], $this->client->calls);
        self::assertNull($cache->get('user'));
    }

    public function testDeleteMissingKeyDoesNotFail(): void
    {
        $cache = new RedisCache($this->client);
        $cache->delete('missing');

        self::assertSame([['del', ['missing']]], $this->client->calls);
    }

    // Erros do cliente

    /** @return iterable<string, array{\Closure(RedisCache): mixed}> */
    public static function operations(): iterable
    {
        yield 'get' => [static fn(RedisCache $cache): mixed => $cache->get('k')];
        yield 'set' => [static function (RedisCache $cache): mixed {
            $cache->set('k', 'v');

            return null;
        }];
        yield 'set com ttl' => [static function (RedisCache $cache): mixed {
            $cache->set('k', 'v', 10);

            return null;
        }];
        yield 'delete' => [static function (RedisCache $cache): mixed {
            $cache->delete('k');

            return null;
        }];
    }

    #[DataProvider('operations')]
    public function testClientExceptionBubblesUnchanged(\Closure $operation): void
    {
        $error = new \RuntimeException('falha no cliente');
        $this->client->throw = $error;
        $cache = new RedisCache($this->client);

        try {
            $operation($cache);
            self::fail('A exceção do cliente deveria subir.');
        } catch (\RuntimeException $caught) {
            self::assertSame($error, $caught);
        }
    }
}
