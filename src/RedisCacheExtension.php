<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\CacheRedis;

use Elavora\Api\Extension\Redis\Contracts\RedisConnectionFactory;
use Elavora\Api\Extension\Redis\RedisConfig;
use Elavora\Api\Extension\Redis\RedisServiceRegistrar;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Contracts\Extension;
use InvalidArgumentException;
use RuntimeException;

final class RedisCacheExtension implements Extension
{
    private readonly RedisConfig $redisConfig;
    private readonly string $prefix;
    private readonly ?int $defaultTtlSeconds;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
        $this->redisConfig = RedisConfig::fromArray($config);
        $this->prefix = self::prefix($config['prefix'] ?? '');
        $this->defaultTtlSeconds = self::ttl($config);
    }

    /**
     * Registra o cache Redis usando a factory Redis compartilhada.
     */
    public function register(Application $application): void
    {
        RedisServiceRegistrar::register($application);

        $application->container()->bind(
            CacheStore::class,
            fn (Container $container): RedisCache => $this->createCache($container)
        );
    }

    private function createCache(Container $container): RedisCache
    {
        $factory = $container->get(RedisConnectionFactory::class);
        if (!$factory instanceof RedisConnectionFactory) {
            throw new RuntimeException('O servico RedisConnectionFactory possui tipo invalido.');
        }

        return new RedisCache(
            redis: $factory->connect($this->redisConfig),
            prefix: $this->prefix,
            defaultTtlSeconds: $this->defaultTtlSeconds
        );
    }

    private static function prefix(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('O prefixo do cache Redis deve ser uma string.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function ttl(array $config): ?int
    {
        if (!array_key_exists('ttl', $config) || $config['ttl'] === null) {
            return null;
        }

        $value = $config['ttl'];
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        throw new InvalidArgumentException('O TTL padrao do cache Redis deve ser um inteiro ou null.');
    }
}
