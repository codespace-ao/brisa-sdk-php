<?php

declare(strict_types=1);

namespace Brisa\Http;

/** O transporte não obteve resposta. `timedOut` distingue o tempo esgotado das outras falhas. */
final class TransportFailure extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $timedOut = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
