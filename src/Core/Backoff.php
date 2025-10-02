<?php

declare(strict_types=1);

namespace Scholarly\Core;

use Closure;
use Random\RandomException;

/**
 * Provides exponential backoff durations with decorated jitter.
 */
class Backoff
{
    /** @var callable|null */
    private $jitter;

    /** @var callable|null */
    private $sleeper;

    public function __construct(
        private readonly float $baseDelay = 0.5,
        private readonly float $maxDelay = 60.0,
        private readonly float $factor = 2.0,
        ?callable              $jitter = null,
        ?callable              $sleeper = null,
    ) {
        $this->jitter  = $jitter;
        $this->sleeper = $sleeper;
    }

    /**
     * @throws RandomException
     */
    public function duration(int $attempt): float
    {
        $attempt = max(0, $attempt);
        $delay   = $this->baseDelay * ($this->factor ** $attempt);
        $delay   = min($delay, $this->maxDelay);

        if ($delay <= 0.0) {
            return 0.0;
        }

        $jitter = $this->jitter;

        if ($jitter instanceof Closure) {
            $value = $jitter($delay, $attempt);

            return $value > 0 ? (float) $value : 0.0;
        }

        $min       = $delay / 2.0;
        $max       = $delay;
        $minMicros = (int) round($min * 1_000_000);
        $maxMicros = max($minMicros, (int) round($max * 1_000_000));

        if ($maxMicros <= 0) {
            return $delay;
        }

        $randomMicros = random_int($minMicros, $maxMicros);

        return $randomMicros / 1_000_000;
    }

    public function sleep(float $seconds): void
    {
        if ($seconds <= 0.0) {
            return;
        }

        if ($this->sleeper instanceof Closure) {
            ($this->sleeper)($seconds);

            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }
}
