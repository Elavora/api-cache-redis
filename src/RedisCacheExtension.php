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
use LogicException;

final class RedisCacheExtension implements Extension
{
    private readonly RedisConfig $redisConfig;
    private readonly string $prefix;
    private readonly ?int $defaultTtlSeconds;

    /**
     * @param array{host?: string, port?: int|string, timeout?: float|int|string,
     *     password?: string|null, database?: int|string|null, prefix?: string, ttl?: int|string|null} $config
     */
    public function __construct(array $config = [])
    {
        $this->redisConfig = RedisConfig::fromArray($config);
        $this->prefix = (string) ($config['prefix'] ?? '');
        $this->defaultTtlSeconds = isset($config['ttl']) ? (int) $config['ttl'] : null;
    }

    /**
     * Registra o cache Redis usando a factory Redis compartilhada.
     */
    public function register(Application $application): void
    {
        RedisServiceRegistrar::register($application);

        $application->container()->bind(
            CacheStore::class,
            fn(Container $container): RedisCache => new RedisCache(
                redis: $this->connectionFactory($container)->connect($this->redisConfig),
                prefix: $this->prefix,
                defaultTtlSeconds: $this->defaultTtlSeconds,
            ),
        );
    }

    /**
     * Obtém a factory Redis do container, garantindo o contrato esperado.
     */
    private function connectionFactory(Container $container): RedisConnectionFactory
    {
        $factory = $container->get(RedisConnectionFactory::class);

        if (!$factory instanceof RedisConnectionFactory) {
            throw new LogicException(sprintf(
                'O binding de %s deve resolver uma instancia dessa interface.',
                RedisConnectionFactory::class,
            ));
        }

        return $factory;
    }
}
