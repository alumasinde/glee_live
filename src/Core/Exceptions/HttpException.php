<?php

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An error whose HTTP status is part of the contract.
 *
 * Extends RuntimeException so existing `catch (RuntimeException)` paths (and
 * tests) keep working; the extra piece is the status the caller should return
 * instead of guessing it from the message text.
 */
class HttpException extends RuntimeException
{
    public function __construct(string $message, int $status = 400, ?Throwable $previous = null)
    {
        $status = ($status >= 400 && $status <= 599) ? $status : 500;

        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
