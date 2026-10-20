# elavora/api-cache-redis

Adapter opcional de cache Redis para o framework Elavora 2.

```bash
composer require elavora/api-cache-redis:^1.0
```

Requisitos: PHP `>=8.3`, `ext-redis`, `elavora/api-framework` `^2.0` e
`elavora/api-redis` `^1.0`. A versão `0.1.1` é a última para o framework `^0.3.1`.

Registre `RedisCacheExtension` com as opções `host`, `port`, `timeout`,
`password`, `database`, `prefix` e `ttl`. Com `CACHE_DRIVER=redis`, o framework 2
registra a extensão sozinho a partir das variáveis `REDIS_*`, `CACHE_PREFIX` e `CACHE_TTL`.

Este pacote usa `elavora/api-redis` para abrir e reutilizar conexões Redis. Se
outra extensão registrar uma implementação própria de `RedisConnectionFactory`,
o cache passa a usar essa factory automaticamente.

A especificação completa está em [docs/USO.md](docs/USO.md).
