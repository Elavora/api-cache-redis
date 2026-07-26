# Guia de uso

## Instalacao

```bash
composer require elavora/api-cache-redis:^1.0
```

Requisitos de runtime:

- PHP `>=8.3`
- `ext-redis`
- `elavora/api-framework` `^1.0`
- `elavora/api-redis` `^1.0`

## Registro

```php
use Elavora\Api\Extension\CacheRedis\RedisCacheExtension;

$application->extend(new RedisCacheExtension([
    'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port' => getenv('REDIS_PORT') ?: '6379',
    'password' => getenv('REDIS_PASSWORD') ?: null,
    'database' => getenv('REDIS_DATABASE') ?: '0',
    'prefix' => 'app:cache:',
    'ttl' => 3600,
]));
```

As opcoes de conexao seguem as validacoes de `RedisConfig`. `prefix` deve ser
string. `ttl` aceita inteiro, string inteira ou `null`.

## Semantica do TTL

- `set('key', $value)` usa o TTL padrao configurado.
- `set('key', $value, 10)` usa 10 segundos, mesmo se o padrao for diferente.
- Sem TTL padrao, `set('key', $value)` grava sem expiracao.
- TTL zero ou negativo remove a chave em vez de manter o valor.

Falhas retornadas por `set`, `setex` ou `del` geram `RuntimeException`. Remover
uma chave inexistente continua sendo uma operacao idempotente e bem-sucedida.
As mensagens nao incluem o valor serializado.

## Qualidade

```bash
composer validate --strict --no-check-publish
composer lint
composer analyse
composer test
composer check
```

`composer check` executa lint portatil, PHPStan nivel 8 e PHPUnit.
