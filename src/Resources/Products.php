<?php

declare(strict_types=1);

namespace Brisa\Resources;

/** Artigos do catálogo e stock. */
final class Products extends Resource
{
    /**
     * Cria ou atualiza o artigo com este código. Permissão `products.manage`.
     *
     * @param array<string, mixed> $body ex.: ['type' => 'P', 'description' => 'Parafuso M8', 'unitPrice' => 150, 'taxCode' => 'IVA-NOR']
     * @return array<string, mixed>
     */
    public function upsert(string $code, array $body, array $options = []): array
    {
        return $this->http->request('PUT', '/api/v1/catalog/products/' . self::seg($code), ['body' => $body], $options)->data;
    }

    /** @return array<string, mixed> */
    public function get(string $code, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/catalog/products/' . self::seg($code), [], $options)->data;
    }

    /**
     * @param array<string, mixed> $params `q`, `category`, `page`, `size`...
     * @return array{items: list<array<string, mixed>>, page: int, size: int, total: int}
     */
    public function list(array $params = [], array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/catalog/products', ['query' => $params], $options)->data;
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return self::paginate(fn (array $p) => $this->list($p, $options), $params);
    }

    /**
     * Entrada, saída ou acerto de stock. Permissão `stock.adjust`.
     *
     * @param array<string, mixed> $body ex.: ['type' => 'IN', 'quantity' => 10, 'note' => '...']
     * @return array<string, mixed>
     */
    public function moveStock(string $code, array $body, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/products/' . self::seg($code) . '/movements', ['body' => $body], self::withKey($options))->data;
    }
}
