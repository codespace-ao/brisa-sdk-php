<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** `429`: demasiados pedidos; espere `retryAfter` segundos. */
class RateLimitException extends ApiException
{
}
