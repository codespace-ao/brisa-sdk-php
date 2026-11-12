<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** A assinatura de um webhook não é válida (ou está fora da tolerância de tempo). */
class WebhookSignatureException extends BrisaException
{
}
