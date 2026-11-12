<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** `409`: o recurso ainda não está pronto (ex.: SAF-T ainda a gerar). */
class ConflictException extends ApiException
{
}
