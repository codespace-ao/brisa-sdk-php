<?php

declare(strict_types=1);

namespace Brisa\Resources;

use Brisa\Exception\BrisaException;
use Brisa\Http\HttpClient;

/** Exportações SAF-T (AO): pedir, acompanhar e descarregar. */
final class Saft extends Resource
{
    /** @var callable(float): void */
    private $sleep;

    public function __construct(HttpClient $http, ?callable $sleep = null)
    {
        parent::__construct($http);
        $this->sleep = $sleep ?? static fn (float $s) => usleep((int) round($s * 1_000_000));
    }

    /**
     * Pede a exportação de um período (AAAA-MM-DD). A geração corre em segundo plano: veja {@see waitUntilReady()}.
     *
     * @return array<string, mixed>
     */
    public function request(string $from, string $to, array $options = []): array
    {
        return $this->http->request('POST', '/api/v1/saft/exports', ['body' => ['from' => $from, 'to' => $to]], $options)->data;
    }

    /** @return list<array<string, mixed>> */
    public function list(?int $limit = null, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/saft/exports', ['query' => ['limit' => $limit]], $options)->data;
    }

    /** @return array<string, mixed> */
    public function get(int $exportId, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/saft/exports/' . self::seg($exportId), [], $options)->data;
    }

    /** O XML de uma exportação `READY` (`409` antes disso). Conta como vários pedidos no limite da chave. */
    public function file(int $exportId, array $options = []): string
    {
        return $this->http->request('GET', '/api/v1/saft/exports/' . self::seg($exportId) . '/file', ['as' => 'text', 'accept' => 'application/xml'], $options)->data;
    }

    /**
     * Espera até a exportação ficar `READY` (ou falha com `FAILED`).
     *
     * @return array<string, mixed>
     */
    public function waitUntilReady(int $exportId, float $intervalSeconds = 3.0, float $timeoutSeconds = 300.0, array $options = []): array
    {
        $until = microtime(true) + $timeoutSeconds;
        while (true) {
            $e = $this->get($exportId, $options);
            $status = $e['status'] ?? null;
            if ($status === 'READY') {
                return $e;
            }
            if ($status === 'FAILED') {
                throw new BrisaException(sprintf('A exportação SAF-T %d falhou: %s', $exportId, $e['error'] ?? 'sem detalhe'));
            }
            if (microtime(true) + $intervalSeconds > $until) {
                throw new BrisaException(sprintf('A exportação SAF-T %d não ficou pronta em %s s (estado %s).', $exportId, $timeoutSeconds, (string) $status));
            }
            ($this->sleep)($intervalSeconds);
        }
    }
}
