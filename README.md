# elavora/api-cache-redis

Adapter opcional de cache Redis para o framework Elavora.

## Requisitos

- PHP `>=8.3`
- `ext-redis`
- `elavora/api-framework` `^1.0`
- `elavora/api-redis` `^1.0`

```php
use Elavora\Api\Extension\CacheRedis\RedisCacheExtension;

$application->extend(new RedisCacheExtension([
    'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port' => getenv('REDIS_PORT') ?: '6379',
    'prefix' => 'app:cache:',
    'ttl' => 3600,
]));
```

O TTL informado em cada `set()` tem precedencia sobre `ttl`. Sem TTL padrao e
sem TTL explicito, a chave nao expira. TTL zero ou negativo remove a chave.
Falhas de `set`, `setex` e `del` sao propagadas como `RuntimeException`.

Consulte [docs/USO.md](docs/USO.md) para o contrato completo.
