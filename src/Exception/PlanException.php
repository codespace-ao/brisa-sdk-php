<?php

declare(strict_types=1);

namespace Brisa\Exception;

/** `402`: o plano não permite (`limite-do-plano`, `plano-sem-api`) ou a subscrição está inativa. */
class PlanException extends ApiException
{
    /** O que se esgotou (`invoices`, `clients`, `products`, `users`), nos erros de limite do plano. */
    public function metric(): ?string
    {
        return isset($this->problem['metric']) ? (string) $this->problem['metric'] : null;
    }

    /** O máximo do plano. */
    public function limit(): ?int
    {
        return isset($this->problem['limit']) ? (int) $this->problem['limit'] : null;
    }
}
