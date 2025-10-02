<?php

declare(strict_types=1);

namespace Scholarly\Core\Exceptions;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Base runtime exception carrying the HTTP response when available.
 */
class Error extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        private ?ResponseInterface $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
