<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** `400`: o corpo não é válido; `errors()` diz quais campos. */
class ValidationException extends ApiException
{
}
