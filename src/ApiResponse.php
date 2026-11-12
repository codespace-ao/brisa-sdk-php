<?php

declare(strict_types=1);

namespace Brisa;

/** A resposta de um pedido com êxito: os dados, o estado, os cabeçalhos e os limites de pedidos. */
final class ApiResponse
{
    /**
     * @param mixed                       $data
     * @param array<string, string>       $headers   nomes em minúsculas
     * @param array{limit: int, remaining: int, reset: int}|null $rateLimit
     */
    public function __construct(
        public readonly mixed $data,
        public readonly int $status,
        public readonly array $headers,
        public readonly bool $replayed,
        public readonly ?array $rateLimit,
    ) {
    }
}
