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
     * @param array<string, mixed> $params `q`, `activeOnly`, `page`, `size`, e a ordem: `sort` (`campo[:asc|:desc][,campo...]` entre `name`, `taxId`, `city`,
     *                                     `createdAt` e, que pedem os totais, `invoiced`, `due`, `lastInvoice`, `documents`), `desc` (`true` inverte um campo que não diga a direção)
     *                                     e `totals` (`true` acrescenta a cada cliente `invoiced`, `amountDue`, `lastInvoiceDate` e `documents`)
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

    /**
     * A conta corrente do cliente: faturas, notas de crédito e recibos dia a dia, com o saldo (positivo = o cliente deve). Permissões `clients.view` e `invoices.view`.
     *
     * @param array{from?: string, to?: string} $period datas AAAA-MM-DD
     * @return array{clientId: int, taxId: string, name: string, from: string, to: string, opening: float|int, entries: list<array<string, mixed>>, debits: float|int, credits: float|int, closing: float|int}
     */
    public function statement(int $clientId, array $period = [], array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/clients/' . self::seg($clientId) . '/statement', ['query' => $period], $options)->data;
    }

    /**
     * O extrato para imprimir (PDF, bytes). Pede também a permissão `reports.export`.
     *
     * @param array{from?: string, to?: string} $period
     */
    public function statementPdf(int $clientId, array $period = [], array $options = []): string
    {
        return $this->http->request('GET', '/api/v1/clients/' . self::seg($clientId) . '/statement', ['query' => $period + ['format' => 'pdf'], 'as' => 'bytes', 'accept' => 'application/pdf'], $options)->data;
    }

    /**
     * O extrato em CSV (texto). Pede também a permissão `reports.export`.
     *
     * @param array{from?: string, to?: string} $period
     */
    public function statementCsv(int $clientId, array $period = [], array $options = []): string
    {
        return $this->http->request('GET', '/api/v1/clients/' . self::seg($clientId) . '/statement', ['query' => $period + ['format' => 'csv'], 'as' => 'text', 'accept' => 'text/csv'], $options)->data;
    }
}
