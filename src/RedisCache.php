<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\CacheRedis;

use Elavora\Api\Extension\Redis\Contracts\RedisClient;
use Elavora\Api\Framework\Contracts\CacheStore;
use RuntimeException;

final class RedisCache implements CacheStore
{
    /**
     * @param RedisClient $redis Cliente Redis reutilizavel.
     * @param string $prefix Prefixo aplicado nas chaves.
     * @param int|null $defaultTtlSeconds TTL padrao em segundos.
     */
    public function __construct(
        private readonly RedisClient $redis,
        private readonly string $prefix = '',
        private readonly ?int $defaultTtlSeconds = null
    ) {
    }

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
        $value = serialize($value);
        $ttlSeconds ??= $this->defaultTtlSeconds;

        if ($ttlSeconds === null) {
            if (!$this->redis->set($key, $value)) {
                throw new RuntimeException('Falha ao gravar o valor no cache Redis.');
            }

            return;
        }

        if ($ttlSeconds <= 0) {
            $this->deleteCacheKey($key);
            return;
        }

        if (!$this->redis->setex($key, $ttlSeconds, $value)) {
            throw new RuntimeException('Falha ao gravar o valor com TTL no cache Redis.');
        }
    }

    /**
     * Remove uma chave do Redis.
     */
    public function delete(string $key): void
    {
        $this->deleteCacheKey($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return $this->prefix . $key;
    }

    private function deleteCacheKey(string $key): void
    {
        if ($this->redis->del($key) === false) {
            throw new RuntimeException('Falha ao remover o valor do cache Redis.');
        }
    }
}
