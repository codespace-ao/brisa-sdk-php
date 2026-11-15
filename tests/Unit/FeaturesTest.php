<?php

declare(strict_types=1);

namespace Brisa\Tests\Unit;

use Brisa\Brisa;
use Brisa\Http\RawResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Papéis (A4, A5, rolos), vendas com vários meios de pagamento, dados bancários, ordem e extrato dos clientes. */
final class FeaturesTest extends TestCase
{
    private function brisa(FakeTransport $t): Brisa
    {
        return new Brisa(['apiKey' => 'brk_sbx_test', 'baseUrl' => 'https://api.example.test', 'transport' => $t, 'sleep' => static function (float $s): void {
        }]);
    }

    /** @return iterable<string, array{string}> */
    public static function formats(): iterable
    {
        foreach (['a4', 'a5', 'thermal', 'thermal80', 'thermal58'] as $format) {
            yield $format => [$format];
        }
    }

    #[DataProvider('formats')]
    public function testThePdfIsAskedForInTheRightFormat(string $format): void
    {
        $t = new FakeTransport([new RawResponse(200, ['content-type' => 'application/pdf'], '%PDF-1.7')]);
        $pdf = $this->brisa($t)->documents->pdf(7, ['format' => $format, 'copy' => 'second']);
        self::assertSame('%PDF-1.7', $pdf);
        self::assertSame('https://api.example.test/api/v1/documents/7/pdf?format=' . $format . '&copy=second', $t->calls[0]['url']);
        self::assertSame('application/pdf', $t->calls[0]['headers']['Accept']);
    }

    public function testSeveralMeansOfPaymentAreSentAsTheyAre(): void
    {
        $t = new FakeTransport([FakeTransport::json(201, ['id' => 9, 'grossTotal' => 114])]);
        $payment = ['payments' => [['method' => 'debit_card', 'amount' => 50], ['method' => 'cash', 'amount' => 100]]];
        $this->brisa($t)->documents->issue(['type' => 'FR', 'lines' => [['description' => 'Artigo', 'quantity' => 1, 'unitPrice' => 100]], 'payment' => $payment]);
        $sent = json_decode((string) $t->calls[0]['body'], true);
        self::assertSame($payment, $sent['payment']);
        self::assertMatchesRegularExpression('/^brisa-php-[0-9a-f]{32}$/', $t->calls[0]['headers']['Idempotency-Key']);
    }

    public function testTheDetailCarriesTheMeansAndTheMixedMethod(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, ['id' => 9, 'paymentMethod' => 'mixed', 'amountReceived' => 150, 'payable' => 114,
            'payments' => [['method' => 'debit_card', 'amount' => 50], ['method' => 'cash', 'amount' => 100]]])]);
        $d = $this->brisa($t)->documents->get(9);
        self::assertSame('mixed', $d['paymentMethod']);
        self::assertCount(2, $d['payments']);
        self::assertSame(36, $d['amountReceived'] - $d['payable'], 'the change');
    }

    public function testTheCompanyBankIsReadAndWritten(): void
    {
        $bank = ['bankName' => 'BAI', 'holder' => 'Ngola Comercial Lda', 'iban' => 'AO06004000006789123456789', 'accountNumber' => '', 'kwikKey' => '923000000', 'expressNumber' => '923000001', 'notes' => ''];
        $t = new FakeTransport([FakeTransport::json(200, $bank), FakeTransport::json(200, $bank)]);
        $b = $this->brisa($t);
        self::assertSame($bank, $b->bank());
        self::assertSame($bank, $b->setBank(['iban' => 'ao06 0040 0000 6789 1234 5678 9', 'bankName' => 'BAI']));
        self::assertSame('https://api.example.test/api/v1/company/bank', $t->calls[0]['url']);
        self::assertSame('PUT', $t->calls[1]['method']);
        self::assertSame(['iban' => 'ao06 0040 0000 6789 1234 5678 9', 'bankName' => 'BAI'], json_decode((string) $t->calls[1]['body'], true));
    }

    public function testAnEmptyBankIsAnObjectAndNotAList(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, [])]);
        $this->brisa($t)->setBank([]);
        self::assertSame('{}', $t->calls[0]['body'], 'clearing the details sends an empty object');
    }

    public function testAnInvoiceCarriesTheCompanysBankAnotherOrNone(): void
    {
        $t = new FakeTransport([FakeTransport::json(201, ['id' => 1]), FakeTransport::json(201, ['id' => 2]), FakeTransport::json(201, ['id' => 3])]);
        $b = $this->brisa($t);
        $ft = ['type' => 'FT', 'clientId' => 4, 'lines' => [['description' => 'Artigo', 'quantity' => 1, 'unitPrice' => 100]]];
        $b->documents->issue($ft);
        $b->documents->issue($ft + ['bankDetails' => ['bankName' => 'BFA', 'kwikKey' => '911111111']]);
        $b->documents->issue($ft + ['printBankDetails' => false]);
        $bodies = array_map(static fn (array $c) => json_decode((string) $c['body'], true), $t->calls);
        self::assertArrayNotHasKey('bankDetails', $bodies[0]);
        self::assertSame(['bankName' => 'BFA', 'kwikKey' => '911111111'], $bodies[1]['bankDetails']);
        self::assertFalse($bodies[2]['printBankDetails']);
    }

    public function testTheClientListIsOrderedAndWithTotals(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, ['items' => [['id' => 1, 'name' => 'Zeta', 'invoiced' => 228, 'amountDue' => 228, 'lastInvoiceDate' => '2026-11-15', 'documents' => 2]], 'page' => 0, 'size' => 50, 'total' => 1])]);
        $page = $this->brisa($t)->clients->list(['sort' => 'invoiced:desc', 'totals' => true, 'desc' => false, 'q' => 'zeta']);
        self::assertSame('https://api.example.test/api/v1/clients?sort=invoiced%3Adesc&totals=true&desc=false&q=zeta', $t->calls[0]['url']);
        self::assertSame(228, $page['items'][0]['invoiced']);
    }

    public function testTheStatementIsReadAndPrinted(): void
    {
        $statement = ['clientId' => 5, 'taxId' => '5000000001', 'name' => 'Ngola', 'from' => '2026-11-01', 'to' => '2026-11-30', 'opening' => 0,
            'entries' => [['date' => '2026-11-03', 'reference' => 'FT FT2026/1', 'type' => 'FT', 'description' => 'Fatura', 'debit' => 114, 'credit' => 0, 'balance' => 114]],
            'debits' => 114, 'credits' => 0, 'closing' => 114];
        $t = new FakeTransport([
            FakeTransport::json(200, $statement),
            new RawResponse(200, ['content-type' => 'application/pdf'], '%PDF-1.7'),
            new RawResponse(200, ['content-type' => 'text/csv'], "Data;Documento\n"),
        ]);
        $b = $this->brisa($t);
        self::assertSame(114, $b->clients->statement(5, ['from' => '2026-11-01', 'to' => '2026-11-30'])['closing']);
        self::assertSame('%PDF-1.7', $b->clients->statementPdf(5, ['from' => '2026-11-01']));
        self::assertStringContainsString('Documento', $b->clients->statementCsv(5));
        self::assertSame('https://api.example.test/api/v1/clients/5/statement?from=2026-11-01&to=2026-11-30', $t->calls[0]['url']);
        self::assertSame('https://api.example.test/api/v1/clients/5/statement?from=2026-11-01&format=pdf', $t->calls[1]['url']);
        self::assertSame('https://api.example.test/api/v1/clients/5/statement?format=csv', $t->calls[2]['url']);
    }

    public function testTheVersionIsTheOneTheChangelogSays(): void
    {
        self::assertSame('0.2.0', Brisa::VERSION);
        self::assertStringContainsString('## 0.2.0', (string) file_get_contents(__DIR__ . '/../../CHANGELOG.md'));
    }
}
