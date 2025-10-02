<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use Scholarly\Core\CacheLayer;

final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[(string) $key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
}

it('remembers values and caches subsequent calls', function () {
    $cache = new CacheLayer(new ArrayCache());
    $counter = 0;

    $value = $cache->remember('foo', function () use (&$counter) {
        return ++$counter;
    });

    $again = $cache->remember('foo', function () use (&$counter) {
        return ++$counter;
    });

    expect($value)->toBe(1)
        ->and($again)->toBe(1);
});

it('builds deterministic cache keys regardless of query ordering', function () {
    $cache = new CacheLayer(new ArrayCache());

    $a = $cache->buildKey('GET', 'https://example.com/works', ['a' => 1, 'b' => 2]);
    $b = $cache->buildKey('GET', 'https://example.com/works', ['b' => 2, 'a' => 1]);

    expect($a)->toBe($b);
});
