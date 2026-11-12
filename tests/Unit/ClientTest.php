<?php

declare(strict_types=1);

namespace Brisa\Tests\Unit;

use Brisa\Brisa;
use Brisa\Exception\ApiException;
use Brisa\Exception\AuthenticationException;
use Brisa\Exception\BrisaException;
use Brisa\Exception\ConflictException;
use Brisa\Exception\ConnectionException;
use Brisa\Exception\NotFoundException;
use Brisa\Exception\PlanException;
use Brisa\Exception\RateLimitException;
use Brisa\Exception\RuleException;
use Brisa\Exception\ServerException;
use Brisa\Exception\TimeoutException;
use Brisa\Exception\ValidationException;
use Brisa\Http\HttpClient;
use Brisa\Http\RawResponse;
use Brisa\Http\TransportFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var list<float> */
    private array $slept = [];

    private function brisa(FakeTransport $t, array $options = []): Brisa
    {
        return new Brisa($options + [
            'apiKey' => 'brk_sbx_test',
            'baseUrl' => 'https://api.example.test/',
            'transport' => $t,
            'sleep' => function (float $s): void {
                $this->slept[] = $s;
            },
        ]);
    }

    public function testNeedsAKey(): void
    {
        putenv('BRISA_API_KEY');
        $this->expectException(BrisaException::class);
        new Brisa(['transport' => new FakeTransport([])]);
    }

    public function testReadsKeyAndAddressFromTheEnvironment(): void
    {
        putenv('BRISA_API_KEY=brk_env');
        putenv('BRISA_API_URL=https://env.example.test');
        try {
            $t = new FakeTransport([FakeTransport::json(200, ['subject' => 'k'])]);
            $b = new Brisa(['transport' => $t]);
            $b->me();
            self::assertSame('https://env.example.test/api/v1/me', $t->calls[0]['url']);
            self::assertSame('Bearer brk_env', $t->calls[0]['headers']['Authorization']);
        } finally {
            putenv('BRISA_API_KEY');
            putenv('BRISA_API_URL');
        }
    }

    public function testSendsTheKeyAndAUserAgent(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, ['name' => 'Ngola'], ['X-RateLimit-Limit' => '120', 'X-RateLimit-Remaining' => '119', 'X-RateLimit-Reset' => '60'])]);
        $b = $this->brisa($t);
        self::assertSame(['name' => 'Ngola'], $b->company());
        $call = $t->calls[0];
        self::assertSame('GET', $call['method']);
        self::assertSame('https://api.example.test/api/v1/company', $call['url']);
        self::assertSame('Bearer brk_sbx_test', $call['headers']['Authorization']);
        self::assertStringStartsWith('brisa-sdk-php/' . Brisa::VERSION, $call['headers']['User-Agent']);
        self::assertSame(['limit' => 120, 'remaining' => 119, 'reset' => 60], $b->rateLimit());
    }

    public function testIssuingADocumentSendsJsonAndAnIdempotencyKey(): void
    {
        $t = new FakeTransport([FakeTransport::json(201, ['id' => 7, 'number' => 'FT 2026/7'])]);
        $doc = $this->brisa($t)->documents->issue(['type' => 'FT', 'clientId' => 42, 'lines' => [['productCode' => 'PAR-M8', 'quantity' => 4]]]);
        self::assertSame('FT 2026/7', $doc['number']);
        $call = $t->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('application/json', $call['headers']['Content-Type']);
        self::assertMatchesRegularExpression('/^brisa-php-[0-9a-f]{32}$/', $call['headers']['Idempotency-Key']);
        self::assertSame(['type' => 'FT', 'clientId' => 42, 'lines' => [['productCode' => 'PAR-M8', 'quantity' => 4]]], json_decode((string) $call['body'], true));
    }

    public function testAGivenIdempotencyKeyIsKept(): void
    {
        $t = new FakeTransport([FakeTransport::json(201, ['id' => 1])]);
        $this->brisa($t)->documents->pay(5, ['method' => 'CASH'], ['idempotencyKey' => 'pedido-123']);
        self::assertSame('pedido-123', $t->calls[0]['headers']['Idempotency-Key']);
        self::assertSame('https://api.example.test/api/v1/documents/5/payments', $t->calls[0]['url']);
    }

    public function testQueryStringsSkipNullsAndRepeatArrays(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, ['items' => [], 'page' => 0, 'size' => 20, 'total' => 0])]);
        $this->brisa($t)->documents->list(['type' => ['FT', 'FR'], 'q' => 'Ngola Lda', 'from' => null, 'size' => 20]);
        self::assertSame('https://api.example.test/api/v1/documents?type=FT&type=FR&q=Ngola%20Lda&size=20', $t->calls[0]['url']);
    }

    public function testPathSegmentsAreEncoded(): void
    {
        $t = new FakeTransport([FakeTransport::json(200, ['code' => 'A/B 1'])]);
        $this->brisa($t)->products->get('A/B 1');
        self::assertSame('https://api.example.test/api/v1/catalog/products/A%2FB%201', $t->calls[0]['url']);
    }

    public function testListAllWalksEveryPage(): void
    {
        $t = new FakeTransport([
            FakeTransport::json(200, ['items' => [['id' => 1], ['id' => 2]], 'page' => 0, 'size' => 2, 'total' => 5]),
            FakeTransport::json(200, ['items' => [['id' => 3], ['id' => 4]], 'page' => 1, 'size' => 2, 'total' => 5]),
            FakeTransport::json(200, ['items' => [['id' => 5]], 'page' => 2, 'size' => 2, 'total' => 5]),
        ]);
        $ids = array_map(static fn (array $c) => $c['id'], iterator_to_array($this->brisa($t)->clients->listAll(['size' => 2]), false));
        self::assertSame([1, 2, 3, 4, 5], $ids);
        self::assertCount(3, $t->calls);
        self::assertStringContainsString('page=2', $t->calls[2]['url']);
    }

    public function testPdfComesBackAsBytes(): void
    {
        $t = new FakeTransport([new RawResponse(200, ['content-type' => 'application/pdf'], "%PDF-1.7\n...")]);
        $pdf = $this->brisa($t)->documents->pdf(9);
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertSame('application/pdf', $t->calls[0]['headers']['Accept']);
    }

    public function testNoContent(): void
    {
        $t = new FakeTransport([new RawResponse(204, [], '')]);
        $this->brisa($t)->documents->cancel(3, ['reason' => 'Erro no cliente']);
        self::assertSame('https://api.example.test/api/v1/documents/3/cancel', $t->calls[0]['url']);
    }

    /** @return iterable<string, array{int, class-string<ApiException>}> */
    public static function errors(): iterable
    {
        yield '400' => [400, ValidationException::class];
        yield '401' => [401, AuthenticationException::class];
        yield '402' => [402, PlanException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, RuleException::class];
        yield '500' => [500, ServerException::class];
    }

    #[DataProvider('errors')]
    public function testProblemsBecomeTypedExceptions(int $status, string $class): void
    {
        $t = new FakeTransport([FakeTransport::problem($status, ['type' => 'urn:brisa:x', 'title' => 'Título', 'detail' => 'Explicação', 'field' => 'lines[0].quantity'])]);
        try {
            $this->brisa($t)->me();
            self::fail('expected an exception');
        } catch (ApiException $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($status, $e->status());
            self::assertSame('urn:brisa:x', $e->type());
            self::assertSame('lines[0].quantity', $e->field());
            self::assertSame($status . ' Título: Explicação', $e->getMessage());
        }
    }

    public function testValidationErrorsAndPlanLimits(): void
    {
        $t = new FakeTransport([
            FakeTransport::problem(400, ['type' => 'urn:brisa:validacao', 'title' => 'Dados inválidos', 'errors' => ['email' => 'inválido', 'name' => ['curto', 'obrigatório']]]),
            FakeTransport::problem(402, ['type' => 'urn:brisa:limite-do-plano', 'title' => 'Limite', 'metric' => 'invoices', 'limit' => 50]),
        ]);
        $b = $this->brisa($t);
        try {
            $b->clients->create(['name' => 'x']);
        } catch (ValidationException $e) {
            self::assertSame(['email' => 'inválido', 'name' => 'curto; obrigatório'], $e->errors());
        }
        try {
            $b->documents->issue(['type' => 'FT']);
        } catch (PlanException $e) {
            self::assertSame('invoices', $e->metric());
            self::assertSame(50, $e->limit());
        }
        self::assertCount(2, $t->calls);
    }

    public function testRetriesAfterRetryAfterAndKeepsTheSameKey(): void
    {
        $t = new FakeTransport([
            FakeTransport::problem(429, ['type' => 'urn:brisa:limite', 'title' => 'Demasiados pedidos'], ['Retry-After' => '2']),
            FakeTransport::json(201, ['id' => 1]),
        ]);
        $this->brisa($t)->documents->issue(['type' => 'FT']);
        self::assertSame([2.0], $this->slept);
        self::assertCount(2, $t->calls);
        self::assertSame($t->calls[0]['headers']['Idempotency-Key'], $t->calls[1]['headers']['Idempotency-Key']);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $t = new FakeTransport(array_fill(0, 3, FakeTransport::problem(503, ['title' => 'Indisponível'], ['Retry-After' => '1'])));
        try {
            $this->brisa($t)->me();
            self::fail('expected an exception');
        } catch (ServerException $e) {
            self::assertTrue($e->isRetryable());
            self::assertSame(1, $e->retryAfter);
        }
        self::assertCount(3, $t->calls);
    }

    public function testDoesNotWaitLongerThanMaxRetryDelay(): void
    {
        $t = new FakeTransport([FakeTransport::problem(429, ['title' => 'Espere'], ['Retry-After' => '3600'])]);
        $this->expectException(RateLimitException::class);
        $this->brisa($t, ['maxRetryDelay' => 60])->me();
    }

    public function testNetworkFailuresRepeatReadsButNotPostsWithoutAKey(): void
    {
        $t = new FakeTransport([new TransportFailure('connection reset'), FakeTransport::json(200, ['ok' => true])]);
        self::assertSame(['ok' => true], $this->brisa($t)->me());
        self::assertCount(2, $t->calls);

        $t2 = new FakeTransport([new TransportFailure('connection reset')]);
        try {
            $this->brisa($t2)->saft->request('2026-01-01', '2026-01-31');
            self::fail('expected an exception');
        } catch (ConnectionException $e) {
            self::assertStringContainsString('connection reset', $e->getMessage());
        }
        self::assertCount(1, $t2->calls, 'a POST without Idempotency-Key is never repeated');
    }

    public function testTimeouts(): void
    {
        $t = new FakeTransport([new TransportFailure('timed out', true)]);
        $this->expectException(TimeoutException::class);
        $this->brisa($t, ['maxRetries' => 0])->me();
    }

    public function testAnHtmlErrorFromAProxyIsStillAnApiException(): void
    {
        $t = new FakeTransport([new RawResponse(502, ['content-type' => 'text/html'], '<html>Bad gateway</html>')]);
        try {
            $this->brisa($t, ['maxRetries' => 0])->me();
            self::fail('expected an exception');
        } catch (ServerException $e) {
            self::assertSame(502, $e->status());
            self::assertSame('<html>Bad gateway</html>', $e->detail());
        }
    }

    public function testSaftWaitsUntilReady(): void
    {
        $t = new FakeTransport([
            FakeTransport::json(200, ['id' => 4, 'status' => 'PENDING']),
            FakeTransport::json(200, ['id' => 4, 'status' => 'READY']),
            new RawResponse(200, ['content-type' => 'application/xml'], '<AuditFile/>'),
        ]);
        $b = $this->brisa($t);
        self::assertSame('READY', $b->saft->waitUntilReady(4, 0.01, 5)['status']);
        self::assertSame('<AuditFile/>', $b->saft->file(4));
        self::assertSame('application/xml', $t->calls[2]['headers']['Accept']);
    }

    public function testRetryAfterAsAnHttpDate(): void
    {
        self::assertSame(30, HttpClient::parseRetryAfter(gmdate('D, d M Y H:i:s \G\M\T', 1_000_030), 1_000_000));
        self::assertSame(5, HttpClient::parseRetryAfter('5'));
        self::assertNull(HttpClient::parseRetryAfter('amanhã'));
    }

    public function testReplayedResponses(): void
    {
        $t = new FakeTransport([FakeTransport::json(201, ['id' => 1], ['Idempotent-Replayed' => 'true'])]);
        $r = $this->brisa($t)->request('POST', '/api/v1/documents', ['body' => ['type' => 'FT']], ['idempotencyKey' => 'abc-12345']);
        self::assertTrue($r->replayed);
        self::assertSame(201, $r->status);
    }
}
