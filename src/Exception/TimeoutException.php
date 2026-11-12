<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** A API não respondeu dentro do tempo (`timeout`). */
class TimeoutException extends ConnectionException
{
}
