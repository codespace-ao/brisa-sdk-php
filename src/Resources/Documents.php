<?php

declare(strict_types=1);

namespace Brisa\Resources;

/** Documentos fiscais: emitir, consultar, PDF, pagamentos, anulações e notas de crédito. */
final class Documents extends Resource
{
    /**
     * Emite `FT`, `FR`, `TV` ou `PF` (`POST /api/v1/documents`). Permissão `invoices.issue`.
     *
     * @param array<string, mixed> $body  ex.: ['type' => 'FT', 'clientId' => 42, 'lines' => [['productCode' => 'PAR-M8', 'quantity' => 4]]]
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function issue(array $body, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/documents', ['body' => $body], self::withKey($options))->data;
    }

    /**
     * Uma página de documentos, do mais recente para o mais antigo. Permissão `invoices.view`.
     *
     * @param array<string, mixed> $params `type`, `status`, `from`, `to`, `q`, `page`, `size`...
     * @return array{items: list<array<string, mixed>>, page: int, size: int, total: int}
     */
    public function list(array $params = [], array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/documents', ['query' => $params], $options)->data;
    }

    /**
     * Todos os documentos que correspondem aos filtros, página a página.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return self::paginate(fn (array $p) => $this->list($p, $options), $params);
    }

    /** @return array<string, mixed> Um documento, com linhas, totais, valor em dívida e estado na AGT. */
    public function get(int $documentId, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/documents/' . self::seg($documentId), [], $options)->data;
    }

    /**
     * O PDF do documento (bytes). Conta como mais de um pedido no limite da chave: guarde-o em vez de o pedir de cada vez.
     *
     * @param array<string, mixed> $params ex.: ['copies' => 2]
     */
    public function pdf(int $documentId, array $params = [], array $options = []): string
    {
        return $this->http->request('GET', '/api/v1/documents/' . self::seg($documentId) . '/pdf', ['query' => $params, 'as' => 'bytes', 'accept' => 'application/pdf'], $options)->data;
    }

    /**
     * O documento como dados: JSON (por omissão, devolvido como array) ou XML (texto). Permissão `invoices.export`.
     *
     * @param 'json'|'xml' $format
     * @return array<string, mixed>|string
     */
    public function export(int $documentId, string $format = 'json', array $options = []): array|string
    {
        $xml = $format === 'xml';
        return $this->http->request('GET', '/api/v1/documents/' . self::seg($documentId) . '/export', [
            'query' => ['format' => $format],
            'as' => $xml ? 'text' : 'json',
            'accept' => $xml ? 'application/xml' : 'application/json',
        ], $options)->data;
    }

    /**
     * Paga o documento, no todo (sem `amount`) ou em parte, e emite o recibo (`RC`). Permissão `invoices.payments`.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function pay(int $documentId, array $body, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/documents/' . self::seg($documentId) . '/payments', ['body' => $body], self::withKey($options))->data;
    }

    /**
     * Liquida várias faturas de um mesmo cliente num recibo global (`RG`).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function payGlobal(array $body, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/documents/payments/global', ['body' => $body], self::withKey($options))->data;
    }

    /**
     * Anula o documento (numa fatura emitida, só com `basis`). Repetido, dá o mesmo resultado. Permissão `invoices.cancel`.
     *
     * @param array<string, mixed> $body ex.: ['reason' => '...', 'basis' => '...']
     */
    public function cancel(int $documentId, array $body, array $options = []): void
    {
        $this->http->request('POST', '/api/v1/documents/' . self::seg($documentId) . '/cancel', ['body' => $body], $options);
    }

    /**
     * Retifica: emite uma nota de crédito (`NC`) que espelha o original.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function rectify(int $documentId, array $body, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/documents/' . self::seg($documentId) . '/rectify', ['body' => $body], self::withKey($options))->data;
    }

    /**
     * Nota de crédito parcial (ou total, sem `lines`).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function creditNote(int $documentId, array $body, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/documents/' . self::seg($documentId) . '/credit-notes', ['body' => $body], self::withKey($options))->data;
    }

    /** @return list<array<string, mixed>> Quanto de cada linha já foi creditado e quanto ainda se pode creditar. */
    public function credits(int $documentId, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/documents/' . self::seg($documentId) . '/credits', [], $options)->data;
    }

    /** @return array<string, mixed> O histórico do documento (auditoria e envios). */
    public function history(int $documentId, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/documents/' . self::seg($documentId) . '/history', [], $options)->data;
    }

    /** Muda a data de vencimento (AAAA-MM-DD, até 365 dias depois da emissão). */
    public function setDueDate(int $documentId, string $dueDate, array $options = []): void
    {
        $this->http->request('PUT', '/api/v1/documents/' . self::seg($documentId) . '/due-date', ['body' => ['dueDate' => $dueDate]], $options);
    }

    /**
     * Envia o documento por e-mail. Com uma chave de API, só ao e-mail do cliente que consta do documento. Permissão `invoices.send`.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function send(int $documentId, array $body = [], array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/documents/' . self::seg($documentId) . '/send', ['body' => (object) $body], $options)->data;
    }
}
