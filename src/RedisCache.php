<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\CacheRedis;

use Elavora\Api\Extension\Redis\Contracts\RedisClient;
use Elavora\Api\Framework\Contracts\CacheStore;

final class RedisCache implements CacheStore
{
    /**
     * @param RedisClient $redis Cliente Redis reutilizavel.
     * @param string $prefix Prefixo aplicado nas chaves.
     * @param int|null $defaultTtlSeconds TTL usado quando a chamada nao informa um; null nao expira.
     */
    public function __construct(
        private readonly RedisClient $redis,
        private readonly string $prefix = '',
        private readonly ?int $defaultTtlSeconds = null,
    ) {}

    /**
     * Recupera um valor serializado do Redis.
     */
    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->cacheKey($key));

        if ($value === false) {
            return null;
        }

        return unserialize($value, ['allowed_classes' => true]);
    }

    /**
     * Armazena um valor serializado no Redis.
     */
    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $key = $this->cacheKey($key);
        $ttlSeconds ??= $this->defaultTtlSeconds;

        if ($ttlSeconds === null) {
            $this->redis->set($key, serialize($value));
            return;
        }

        if ($ttlSeconds <= 0) {
            $this->redis->del($key);
            return;
        }

        $this->redis->setex($key, $ttlSeconds, serialize($value));
    }

    /**
     * Remove uma chave do Redis.
     */
    public function delete(string $key): void
    {
        $this->redis->del($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
