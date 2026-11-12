<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** `401`: chave inválida, expirada, revogada, de outro ambiente ou de um endereço não autorizado. */
class AuthenticationException extends ApiException
{
}
