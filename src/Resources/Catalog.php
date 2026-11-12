<?php

declare(strict_types=1);

namespace Brisa\Resources;

/** Impostos, motivos de isenção e categorias. */
final class Catalog extends Resource
{
    /** @return list<array<string, mixed>> Os impostos configurados na empresa. */
    public function taxes(bool $activeOnly = false, array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/catalog/taxes', ['query' => $activeOnly ? ['activeOnly' => true] : null], $options)->data;
    }

    /** @return list<array<string, mixed>> Os motivos de isenção da AGT (`M00`...). */
    public function exemptionReasons(array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/catalog/exemption-reasons', [], $options)->data;
    }

    /** @return list<array<string, mixed>> */
    public function categories(array $options = []): array
    {
        return $this->http->request('GET', '/api/v1/catalog/categories', [], $options)->data;
    }
}
