<?php

declare(strict_types=1);

namespace Brisa;

use Brisa\Http\HttpClient;
use Brisa\Resources\Catalog;
use Brisa\Resources\Clients;
use Brisa\Resources\Documents;
use Brisa\Resources\Products;
use Brisa\Resources\Saft;

/**
 * Cliente da API Brisa.
 *
 * ```php
 * $brisa = new \Brisa\Brisa(['apiKey' => getenv('BRISA_API_KEY')]);
 * $doc = $brisa->documents->issue(['type' => 'FT', 'clientId' => 42, 'lines' => [['productCode' => 'PAR-M8', 'quantity' => 4]]]);
 * ```
 */
final class Brisa
{
    public const VERSION = '0.1.0';

    public readonly Documents $documents;
    public readonly Clients $clients;
    public readonly Products $products;
    public readonly Catalog $catalog;
    public readonly Saft $saft;
    private readonly HttpClient $http;

    /**
     * @param array{
     *     apiKey?: string,
     *     baseUrl?: string,
     *     timeout?: float,
     *     maxRetries?: int,
     *     maxRetryDelay?: float,
     *     headers?: array<string, string>,
     *     transport?: \Brisa\Http\Transport,
     *     sleep?: callable(float): void
     * } $options
     *   - `apiKey`: a chave de API (`brk_...` em produção, `brk_sbx...` na sandbox). Por omissão, a variável de ambiente `BRISA_API_KEY`.
     *   - `baseUrl`: por omissão `https://api.brisa.ao` (ou `BRISA_API_URL`). A chave escolhe o ambiente, não o endereço.
     *   - `timeout`: segundos de cada tentativa (30). `maxRetries`: repetições em 429, 502, 503 e falhas de rede (2).
     *   - `maxRetryDelay`: espera máxima aceite de um `Retry-After`, em segundos (60); acima disso o erro sobe logo.
     */
    public function __construct(array $options = [])
    {
        $this->http = new HttpClient($options);
        $this->documents = new Documents($this->http);
        $this->clients = new Clients($this->http);
        $this->products = new Products($this->http);
        $this->catalog = new Catalog($this->http);
        $this->saft = new Saft($this->http, $options['sleep'] ?? null);
    }

    public function baseUrl(): string
    {
        return $this->http->baseUrl;
    }

    /** @return array{limit: int, remaining: int, reset: int}|null os limites de pedidos indicados na última resposta (`X-RateLimit-*`) */
    public function rateLimit(): ?array
    {
        return $this->http->lastRateLimit;
    }

    /** @return array<string, mixed> Quem é a chave e que permissões tem (`GET /api/v1/me`). */
    public function me(array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/me', [], $options)->data;
    }

    /** @return array<string, mixed> Os dados da empresa da chave (`GET /api/v1/company`). */
    public function company(array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/company', [], $options)->data;
    }

    /**
     * Um pedido qualquer à API, com autenticação, repetições e erros do SDK; devolve também o estado e os cabeçalhos.
     *
     * @param 'GET'|'POST'|'PUT'|'DELETE' $method
     * @param array{query?: array<string, mixed>|null, body?: mixed, as?: 'json'|'text'|'bytes', accept?: string} $request
     * @param array{idempotencyKey?: string, timeout?: float, maxRetries?: int, headers?: array<string, string>} $options
     */
    public function request(string $method, string $path, array $request = [], array $options = []): ApiResponse
    {
        return $this->http->request($method, $path, $request, $options);
    }
}
