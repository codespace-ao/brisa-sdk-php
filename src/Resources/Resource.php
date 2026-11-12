<?php

declare(strict_types=1);

namespace Brisa\Resources;

use Brisa\Http\HttpClient;

/** @internal */
abstract class Resource
{
    public function __construct(protected readonly HttpClient $http)
    {
    }

    protected static function seg(int|string $v): string
    {
        return rawurlencode((string) $v);
    }

    /**
     * As operações que precisam (ou recomendam) um `Idempotency-Key` recebem um aqui quando não foi indicado, para que uma repetição use o mesmo.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function withKey(array $options): array
    {
        $options['idempotencyKey'] ??= HttpClient::idempotencyKey();
        return $options;
    }

    /**
     * Percorre todas as páginas de uma lista, pedindo a seguinte só quando é precisa.
     *
     * @param callable(array<string, mixed>): array{items: list<mixed>, page: int, size: int, total: int} $fetchPage
     * @param array<string, mixed> $params
     * @return \Generator<int, mixed>
     */
    protected static function paginate(callable $fetchPage, array $params): \Generator
    {
        $start = (int) ($params['page'] ?? 0);
        $size = (int) ($params['size'] ?? 100);
        $page = $start;
        $seen = 0;
        while (true) {
            $p = $fetchPage(['page' => $page, 'size' => $size] + $params);
            $items = $p['items'] ?? [];
            foreach ($items as $item) {
                yield $item;
            }
            $seen += count($items);
            if (count($items) === 0 || count($items) < (int) ($p['size'] ?? $size) || $seen + $start * $size >= (int) ($p['total'] ?? 0)) {
                return;
            }
            $page++;
        }
    }
}
