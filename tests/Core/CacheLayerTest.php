<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use Scholarly\Core\CacheLayer;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

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

final class ArrayCacheItem implements CacheItemInterface
{
    private mixed $value;
    private bool $hit;

    public function __construct(private string $key, bool $hit = false, mixed $value = null)
    {
        $this->hit   = $hit;
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit   = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}

final class ArrayCachePool implements CacheItemPoolInterface
{
    /** @var array<string, ArrayCacheItem> */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        return $this->items[$key] ?? new ArrayCacheItem($key);
    }

    /**
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->items[$item->getKey()] = new ArrayCacheItem($item->getKey(), true, $item->get());

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

it('remembers values and caches subsequent calls', function () {
    $cache   = new CacheLayer(new ArrayCache());
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

it('stores values via PSR-6 cache pools', function (): void {
    $cache   = new CacheLayer(new ArrayCachePool());
    $counter = 0;

    $value  = $cache->remember('psr6', function () use (&$counter) { return ++$counter; });
    $again  = $cache->remember('psr6', function () use (&$counter) { return ++$counter; });

    expect($value)->toBe(1)
        ->and($again)->toBe(1);
});
