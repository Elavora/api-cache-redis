# elavora/api-cache-redis

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-cache-redis.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-redis)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-cache-redis.svg?style=flat-square)](https://packagist.org/packages/elavora/api-cache-redis)
[![Qualidade](https://github.com/Elavora/api-cache-redis/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-redis/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-cache-redis/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-cache-redis/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-cache-redis.svg?style=flat-square)](LICENSE)

Adapter opcional de cache Redis para o framework Elavora 2.

- Versão `1.0.0`: exige `elavora/api-framework` `^2.0`, `elavora/api-redis` `^1.0`, PHP `>=8.3` e `ext-redis`.
- Para o framework `^0.3.1`, use a versão `0.1.1`.

## Instalação

```bash
composer require elavora/api-cache-redis:^1.0
```

Requisitos: PHP `>=8.3`, `ext-redis`, `elavora/api-framework` `^2.0` e
`elavora/api-redis` `^1.0`.

## Uso

Registre `RedisCacheExtension` com as opções `host`, `port`, `timeout`,
`password`, `database`, `prefix` e `ttl`. Com `CACHE_DRIVER=redis`, o framework 2
registra a extensão sozinho a partir das variáveis `REDIS_*`, `CACHE_PREFIX` e `CACHE_TTL`.

Este pacote usa `elavora/api-redis` para abrir e reutilizar conexões Redis. Se
outra extensão registrar uma implementação própria de `RedisConnectionFactory`,
o cache passa a usar essa factory automaticamente.

A especificação completa está em [docs/USO.md](docs/USO.md).

## Desenvolvimento e testes

Você só precisa de Docker. O ambiente é a imagem `ghcr.io/elavora/php-dev:8.3-latest`: abra o repositório no VS Code
com **Dev Containers: Reopen in Container**, ou rode no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

A imagem traz `ext-redis`, e os testes não precisam de servidor Redis: usam clientes falsos. Detalhes, e o que roda no
CI, em [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md).

Scripts do Composer:

```bash
composer check     # lint, testes e PHPStan
composer test      # testes PHPUnit
composer lint      # sintaxe (elavora-standards lint)
composer analyse   # PHPStan, nível 8
```

O código segue o padrão PER-CS, com as regras do pacote `elavora/code-standards`. No devcontainer, o arquivo é
formatado ao salvar; no PR, o CI formata o que faltou e commita a correção (`style: organiza o código`).
Os testes rodam no CI, no workflow **Qualidade**, em todo PR. O passo a passo, o que roda no CI e como reproduzi-lo
estão em [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md).

## Como contribuir

1. Crie uma branch a partir da `main` (`feat/...`, `fix/...` ou `docs/...`).
2. Faça commits em português do Brasil, no padrão Conventional Commits (`feat: ...`, `fix: ...`, `docs: ...`).
3. Rode `composer check` antes de abrir o PR.
4. Abra o PR para a `main`, preencha o template e aplique as labels que definem a versão
   ([Versões e release](docs/AUTOMACAO.md#versões-e-release)).
5. Confira o comentário do pipeline de qualidade no PR. Ele não bloqueia o merge, mas quem revisa olha o resultado.

## Versões

Cada PR integrado na `main` gera uma versão automaticamente, conforme as labels do PR: sem a label `release` sai uma
beta (`1.1.0-beta.1`), com ela sai a estável (`1.1.0`). A `1.0.0` sai do PR de upgrade e adoção do pipeline
(`upgrade` mais `release`, a partir da `0.1.1`). As tags novas não têm o prefixo `v`, e as notas de cada
GitHub Release são geradas em português a partir dos PRs. Veja [Versões e release](docs/AUTOMACAO.md#versões-e-release).

## Documentação

1. [Guia de uso](docs/USO.md): a especificação do pacote, com a API pública, a normalização das opções e o TTL.
2. [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md): ambiente, como rodar os testes e o que roda no CI.
3. [Automação do repositório](docs/AUTOMACAO.md): workflows, Dependabot, estilo de código, labels e versões.

## Licença

MIT
