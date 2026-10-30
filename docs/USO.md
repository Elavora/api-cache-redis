# Guia de uso

Adapter opcional de cache Redis para o framework Elavora 2.

Este guia é a especificação do pacote. Tudo o que está aqui é comportamento
garantido e pode ser coberto por teste.

## Instalação

```bash
composer require elavora/api-cache-redis:^1.0
```

## Versão e compatibilidade

| Item | Valor |
|---|---|
| Versão deste pacote | `1.0.0` (primeira versão compatível com o framework 2) |
| `php` | `>=8.3` |
| `ext-redis` | `*` |
| `elavora/api-framework` | `^2.0` |
| `elavora/api-redis` | `^1.0` |

A `1.0.0` sai do PR único de upgrade e adoção do pipeline central, com as labels `upgrade` e `release`. O
fluxo central sobe o major da última tag legada (`v0.1.1`, que conta como `0.1.1`) e publica a `1.0.0`
([Versões e release](AUTOMACAO.md#versões-e-release)).

Não existe compatibilidade com o framework `0.x` nem `1.x`. A versão `0.1.1` deste
pacote continua sendo a última para o framework `^0.3.1`.

O framework 2 exige PHP 8.3. Por isso este pacote também passa a exigir PHP 8.3.
O contrato de extensão do framework não mudou (`Extension::register(Application)`,
`Container` com `has()`, `bind()`, `instance()` e `get()`).

### `composer.json`

O `composer.json` do pacote deve ter:

- `require.php` = `">=8.3"`
- `require.ext-redis` = `"*"`
- `require.elavora/api-framework` = `"^2.0"`
- `require.elavora/api-redis` = `"^1.0"`
- `extra.branch-alias.dev-main` = `"1.0-dev"`
- `autoload.psr-4` = `"Elavora\\Api\\Extension\\CacheRedis\\": "src/"` (sem mudança)
- `composer validate --strict --no-check-publish` sem erros.

## API pública

Namespace: `Elavora\Api\Extension\CacheRedis`.

### `RedisCache`

```php
final class RedisCache implements \Elavora\Api\Framework\Contracts\CacheStore
{
    public function __construct(
        \Elavora\Api\Extension\Redis\Contracts\RedisClient $redis,
        string $prefix = '',
        ?int $defaultTtlSeconds = null,
    );
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void;
    public function delete(string $key): void;
}
```

Os argumentos do construtor podem ser passados por nome (`redis:`, `prefix:`,
`defaultTtlSeconds:`).

`RedisClient` é a interface do `elavora/api-redis` (`get`, `set`, `setex`, `del`,
`rPush`, `lPop`). Nos testes, use uma classe falsa que implementa essa interface e
guarda as chamadas recebidas. Este pacote não trata erros do cliente: uma exceção
lançada pelo `RedisClient` sobe sem mudança.

### `RedisCacheExtension`

```php
final class RedisCacheExtension implements \Elavora\Api\Framework\Contracts\Extension
{
    /**
     * @param array{host?: string, port?: int|string, timeout?: float|int|string,
     *     password?: string|null, database?: int|string|null, prefix?: string, ttl?: int|string|null} $config
     */
    public function __construct(array $config = []);
    public function register(\Elavora\Api\Framework\Application $application): void;
}
```

O construtor recebe um único array, porque é assim que o framework cria a extensão.

## Regras do cache (`RedisCache`)

### Chaves e prefixo

A chave gravada no Redis é `prefix . key`. Exemplo: com `prefix: 'app:'`,
`set('user', 1)` grava a chave Redis `app:user`.

Duas instâncias com prefixos diferentes não enxergam os valores uma da outra.

### Formato do valor

O valor gravado é `serialize($value)`. A leitura usa `unserialize()`.

Exemplo com um cliente falso:

```php
$cache = new RedisCache($client, prefix: 'app:');
$cache->set('a', ['x' => 1]);
// o cliente recebeu set('app:a', 'a:1:{s:1:"x";i:1;}')
```

### Leitura

- `get()` chama `RedisClient::get(prefix . key)`.
- Se o cliente devolver `false` (chave inexistente ou expirada), `get()` devolve `null`.
- Senão, devolve o valor desserializado, com o mesmo tipo que foi gravado
  (arrays, inteiros, strings, `false`, `null`, objetos).
- Um `false` guardado volta como `false`, e não como ausência:

```php
$cache = new RedisCache($client);
$cache->set('flag', false);   // grava 'b:0;'
$cache->get('flag');          // false
$cache->get('missing');       // null
```

### TTL

O TTL usado por `set()` é escolhido assim:

1. `$ttlSeconds` informado na chamada, se não for `null`;
2. senão, o `defaultTtlSeconds` do construtor;
3. se os dois forem `null`, o item fica sem expiração.

Com o TTL escolhido:

| TTL efetivo | Chamada no `RedisClient` |
|---|---|
| `null` | `set(chave, valor)` |
| maior que zero | `setex(chave, ttl, valor)` |
| zero ou negativo | `del(chave)`; nada é gravado |

Exemplos (`$client` é um cliente falso):

```php
$cache = new RedisCache($client, prefix: 'ttl:', defaultTtlSeconds: 60);
$cache->set('key', 'stored');      // setex('ttl:key', 60, 's:6:"stored";')
$cache->set('key', 'ignored', 0);  // del('ttl:key')

$cache = new RedisCache($client, defaultTtlSeconds: 0);
$cache->set('key', 'value');       // del('key'); nada é gravado

$cache = new RedisCache($client, defaultTtlSeconds: 0);
$cache->set('key', 'value', 30);   // o TTL da chamada vence: setex('key', 30, 's:5:"value";')

$cache = new RedisCache($client);
$cache->set('key', 'value');       // set('key', 's:5:"value";')
$cache->set('key', 'value', -5);   // del('key')
```

### Remoção

`delete()` chama `RedisClient::del(prefix . key)`. Remover uma chave que não existe
não gera erro.

## Configuração da extensão (`RedisCacheExtension`)

| Opção | Tipo | Padrão | Uso |
|---|---|---|---|
| `host` | `string` | `'127.0.0.1'` | conexão Redis |
| `port` | `int\|string` | `6379` | conexão Redis |
| `timeout` | `float\|int\|string` | `0.0` | conexão Redis |
| `password` | `string\|null` | `null` | conexão Redis |
| `database` | `int\|string\|null` | `null` | conexão Redis |
| `prefix` | `string` | `''` | vira o `prefix` do `RedisCache` |
| `ttl` | `int\|string\|null` | `null` | vira o `defaultTtlSeconds` do `RedisCache` |

Normalização:

- As cinco opções de conexão viram um `RedisConfig` por `RedisConfig::fromArray($config)`,
  com as regras do `elavora/api-redis` 1.0. Exemplos: `'port' => '6380'` vira `6380`;
  `'database' => '2'` vira `2`; `'database' => '0'` vira `0`; `'password' => ''` vira `null`.
- `prefix` é convertido para string (`(string)`). Ausente ou `null` → `''`.
- `ttl` é convertido para inteiro (`(int)`) quando não é `null`. Exemplo: `'60'` vira `60`.
- `ttl` ausente ou `null` significa sem expiração.
- `ttl` zero ou negativo é aceito e segue a regra do TTL acima.
- Chaves desconhecidas em `$config` são ignoradas, sem erro.
- O construtor não abre conexão e não lança exceção.

### O que `register()` faz

1. Chama `RedisServiceRegistrar::register($application)` do `elavora/api-redis`.
   - Se o container já tem `RedisConnectionFactory::class`, ela é mantida.
   - Senão, o container ganha um `RedisConnectionManager` que usa
     `NativeRedisConnectionFactory`.
2. Liga `Elavora\Api\Framework\Contracts\CacheStore` no container a uma função que cria
   o `RedisCache`:
   - pega `RedisConnectionFactory::class` do container;
   - chama `connect()` com o `RedisConfig` montado no construtor;
   - cria `new RedisCache(cliente, prefix, ttl)`.

Consequências:

- `register()` não abre conexão. A conexão só abre no primeiro
  `container()->get(CacheStore::class)`.
- O container guarda a instância resolvida. Duas chamadas a
  `container()->get(CacheStore::class)` devolvem o mesmo objeto, e a factory recebe
  `connect()` uma vez só.
- Se outra extensão (por exemplo `RedisExtension($factory)` do `api-redis`) registrou a
  factory antes, o cache usa essa factory.

Exemplos (`$factory` é uma `RedisConnectionFactory` falsa que conta as chamadas,
guarda o último `RedisConfig` e devolve um cliente falso):

| Cenário | Resultado |
|---|---|
| `instance(RedisConnectionFactory::class, $factory)`, depois `extend(new RedisCacheExtension(['host' => 'redis', 'database' => 0, 'prefix' => 'cache:']))` | `get(CacheStore::class)` é `RedisCache`; `$factory` chamada 1 vez; config com host `'redis'` e database `0` |
| Mesmo cenário, sem chamar `get(CacheStore::class)` | `$factory` chamada 0 vezes |
| Mesmo cenário, `get(CacheStore::class)` duas vezes | mesmo objeto; `$factory` chamada 1 vez |
| `instance(...$factory)`, `extend(new RedisCacheExtension(['prefix' => 'p:', 'ttl' => '30']))`, `get(CacheStore::class)->set('k', 'v')` | o cliente falso recebeu `setex('p:k', 30, 's:1:"v";')` |
| `instance(...$factory)`, `extend(new RedisCacheExtension())`, `get(CacheStore::class)->set('k', 'v')` | o cliente falso recebeu `set('k', 's:1:"v";')`; config com host `'127.0.0.1'`, port `6379`, database `null` |
| `instance(...$factory)`, `extend(new RedisCacheExtension(['host' => 'redis', 'port' => '6380', 'password' => '', 'database' => '2', 'extra' => 1]))`, `get(CacheStore::class)` | config com port `6380`, password `null`, database `2`; nenhum erro pela chave `extra` |
| Container vazio, `extend(new RedisCacheExtension(['host' => 'redis']))` | `has(RedisConnectionFactory::class)` é `true`; `get(RedisConnectionFactory::class)` é `RedisConnectionManager`; nenhuma conexão aberta |

Registro manual:

```php
use Elavora\Api\Extension\CacheRedis\RedisCacheExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;

$application = Application::create()->extend(new RedisCacheExtension([
    'host' => 'redis',
    'prefix' => 'app:',
    'ttl' => 3600,
]));

$cache = $application->container()->get(CacheStore::class); // RedisCache
```

## Uso pelo bootstrap do framework 2

O framework 2 carrega a extensão sozinho a partir do ambiente, por meio de
`Elavora\Api\Framework\Bootstrap\EnvironmentExtensions`. Este pacote não lê
variáveis de ambiente.

Com `CACHE_DRIVER=redis`, o framework cria `new RedisCacheExtension($config)` com:

| Variável | Chave no array | Valor quando vazia ou ausente |
|---|---|---|
| `REDIS_HOST` | `host` | `'redis'` |
| `REDIS_PORT` | `port` (int) | `6379` |
| `REDIS_PASSWORD` | `password` (string) | `null` |
| `REDIS_DATABASE` | `database` (string, ex.: `'2'`) | `null` |
| `CACHE_PREFIX` | `prefix` | `'api:cache:'` |
| `CACHE_TTL` | `ttl` (int) | chave ausente |

Exemplo testável sem Redis real:

```php
use Elavora\Api\Extension\CacheRedis\RedisCacheExtension;
use Elavora\Api\Extension\Redis\Contracts\RedisConnectionFactory;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\CacheStore;

$extensions = EnvironmentExtensions::load('/app', [
    'CACHE_DRIVER' => 'redis',
    'REDIS_DATABASE' => '2',
    'CACHE_TTL' => '30',
]);
// lista com um único item, instância de RedisCacheExtension

$application = Application::create();
$application->container()->instance(RedisConnectionFactory::class, $factory);
$application->extend($extensions[0]);
$application->container()->get(CacheStore::class)->set('k', 'v');
// $factory recebeu host 'redis', port 6379, password null, database 2
// o cliente falso recebeu setex('api:cache:k', 30, 's:1:"v";')
```

## Validação local

Os testes rodam no container `ghcr.io/elavora/php-dev:8.3-latest`, que já traz `ext-redis`. Os testes unitários usam
um `RedisClient` falso: não precisam de servidor Redis. O comando completo, igual ao do CI, está em
[Desenvolvimento e testes](DESENVOLVIMENTO.md#validar-como-o-ci). Para só rodar a suíte, no PowerShell, na raiz do repo:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer test'
```
## Observações

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensões no bootstrap da aplicação.
- Instale apenas os módulos que a aplicação realmente usa.
