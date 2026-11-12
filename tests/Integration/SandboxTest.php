<?php

declare(strict_types=1);

namespace Brisa\Tests\Integration;

use Brisa\Brisa;
use PHPUnit\Framework\TestCase;

/**
 * Contra uma API real, com uma chave de sandbox (`BRISA_API_KEY`, `brk_sbx...`) e o endereço (`BRISA_API_URL`). Sem elas, os testes são saltados.
 * Só lê e cria dados de sandbox: nada vai à AGT.
 */
final class SandboxTest extends TestCase
{
    private Brisa $brisa;

    protected function setUp(): void
    {
        $key = getenv('BRISA_API_KEY');
        if (!$key || !str_starts_with($key, 'brk_sbx')) {
            self::markTestSkipped('Defina BRISA_API_KEY (de sandbox) e BRISA_API_URL para correr os testes de integração.');
        }
        $this->brisa = new Brisa();
    }

    public function testWhoAmI(): void
    {
        $me = $this->brisa->me();
        self::assertNotEmpty($me);
        self::assertNotNull($this->brisa->rateLimit());
    }

    public function testCatalogAndLists(): void
    {
        self::assertIsArray($this->brisa->catalog->taxes());
        self::assertIsArray($this->brisa->catalog->exemptionReasons());
        $page = $this->brisa->documents->list(['size' => 5]);
        self::assertArrayHasKey('items', $page);
        self::assertArrayHasKey('total', $page);
    }
}
