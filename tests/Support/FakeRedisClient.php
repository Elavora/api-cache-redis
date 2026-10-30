<?php

declare(strict_types=1);

use Elavora\Api\Extension\Redis\Contracts\RedisClient;

/**
 * RedisClient falso: grava cada chamada recebida e guarda os valores em memória.
 * Não precisa de Redis real nem da classe \Redis.
 */
final class FakeRedisClient implements RedisClient
{
    /** @var list<array{0: string, 1: list<mixed>}> */
    public array $calls = [];

    /** @var array<string, string> */
    public array $store = [];

    public ?\Throwable $throw = null;

    public function get(string $key): string|false
    {
        $this->record('get', [$key]);

        return $this->store[$key] ?? false;
    }

    public function set(string $key, string $value): bool
    {
        $this->record('set', [$key, $value]);
        $this->store[$key] = $value;

        return true;
    }

    public function setex(string $key, int $ttlSeconds, string $value): bool
    {
        $this->record('setex', [$key, $ttlSeconds, $value]);
        $this->store[$key] = $value;

        return true;
    }

    public function del(string ...$keys): int|false
    {
        $this->record('del', array_values($keys));
        $removed = 0;
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->store)) {
                unset($this->store[$key]);
                $removed++;
            }
        }

        return $removed;
    }

    public function rPush(string $key, string $value): int|false
    {
        $this->record('rPush', [$key, $value]);

        return 1;
    }

    public function lPop(string $key): string|false
    {
        $this->record('lPop', [$key]);

        return false;
    }

    /** @return list<array{0: string, 1: list<mixed>}> */
    public function callsTo(string $method): array
    {
        return array_values(array_filter($this->calls, static fn(array $call): bool => $call[0] === $method));
    }

    /** @return list<array{0: string, 1: list<mixed>}> chamadas que gravam valor (set/setex) */
    public function writes(): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn(array $call): bool => $call[0] === 'set' || $call[0] === 'setex',
        ));
    }

    /** @param list<mixed> $args */
    private function record(string $method, array $args): void
    {
        $this->calls[] = [$method, $args];
        if ($this->throw !== null) {
            throw $this->throw;
        }
    }
}
