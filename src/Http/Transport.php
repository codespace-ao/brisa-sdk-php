<?php

declare(strict_types=1);

namespace Brisa\Http;

/**
 * Envia um pedido HTTP e devolve a resposta tal como chegou. O SDK usa o {@see CurlTransport}; passe outro (por exemplo, sobre Guzzle ou um
 * duplo nos testes) em `new Brisa(['transport' => ...])`.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     *
     * @throws TransportFailure quando não houve resposta (rede, DNS, TLS ou tempo esgotado)
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): RawResponse;
}
