<?php

declare(strict_types=1);

namespace Scholarly\Core\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Throwable;

class RateLimitException extends Error
{
    public function __construct(
        string                $message = 'Too many requests',
        int                   $code = 429,
        private readonly ?int $retryAfter = null,
        ?ResponseInterface    $response = null,
        ?Throwable            $previous = null,
    ) {
        parent::__construct($message, $code, $response, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
