<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** `422`: uma regra fiscal recusou a operação (ou um `Idempotency-Key` foi reutilizado com outro corpo). */
class RuleException extends ApiException
{
}
