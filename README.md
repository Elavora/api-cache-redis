# elavora/api-cache-redis

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-cache-redis.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-redis)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-cache-redis.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-redis)
[![Composer Quality](https://github.com/Elavora/api-cache-redis/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-redis/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-cache-redis/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-redis/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-cache-redis.svg?style=flat-square)](LICENSE)
Adapter opcional de cache Redis para o framework Elavora.

Registre `RedisCacheExtension` com as opcoes `host`, `port`, `timeout`,
`password`, `database` e `prefix` conforme a necessidade da aplicacao.

Este pacote usa `elavora/api-redis` para abrir e reutilizar conexoes Redis. Se
outra extensao registrar uma implementacao propria de `RedisConnectionFactory`,
o cache passa a usar essa factory automaticamente.
