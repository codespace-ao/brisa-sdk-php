<?php

declare(strict_types=1);

namespace Brisa\Resources;

/** Clientes da empresa. */
final class Clients extends Resource
{
    /**
     * Cria um cliente. Permissão `clients.manage`.
     *
     * @param array<string, mixed> $body ex.: ['name' => 'Ngola Comercial Lda', 'taxId' => '5000000000', 'email' => '...']
     * @return array{id: int}
     */
    public function create(array $body, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/clients', ['body' => $body], self::withKey($options))->data;
    }

    /** @param array<string, mixed> $body os dados completos do cliente (incluindo `taxId`) */
    public function update(int $clientId, array $body, array $options = []): void
    {
        $this->http->request('PUT', '/api/v1/clients/' . self::seg($clientId), ['body' => $body], $options);
    }

    /** @return array<string, mixed> */
    public function get(int $clientId, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/clients/' . self::seg($clientId), [], $options)->data;
    }

    /**
     * @param array<string, mixed> $params `q`, `active`, `page`, `size`...
     * @return array{items: list<array<string, mixed>>, page: int, size: int, total: int}
     */
    public function list(array $params = [], array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/clients', ['query' => $params], $options)->data;
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return self::paginate(fn (array $p) => $this->list($p, $options), $params);
    }

    /** Ativa ou desativa o cliente. Permissão `clients.delete`. */
    public function setActive(int $clientId, bool $active, array $options = []): void
    {
        $this->http->request('PUT', '/api/v1/clients/' . self::seg($clientId) . '/active', ['body' => ['active' => $active]], $options);
    }
}
