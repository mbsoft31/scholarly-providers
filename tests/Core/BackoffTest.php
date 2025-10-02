<?php

declare(strict_types=1);

use Scholarly\Core\Backoff;

it('uses custom jitter for deterministic delays', function (): void {
    $calls = [];

    $backoff = new Backoff(
        baseDelay: 1.0,
        maxDelay: 10.0,
        factor: 2.0,
        jitter: function (float $delay, int $attempt) use (&$calls): float {
            $calls[] = [$delay, $attempt];

            return $delay / 2.0;
        }
    );

    $duration = $backoff->duration(2);

    expect($duration)->toBe(2.0)
        ->and($calls[0][0])->toBe(4.0)
        ->and($calls[0][1])->toBe(2);
});

it('invokes custom sleeper when sleeping', function (): void {
    $sleeps = [];

    $backoff = new Backoff(
        baseDelay: 0.5,
        maxDelay: 1.0,
        factor: 2.0,
        jitter: static fn (): float => 0.5,
        sleeper: function (float $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        }
    );

    $backoff->sleep(0.75);
    $backoff->sleep(-1.0); // ignored

    expect($sleeps)->toBe([0.75]);
});

