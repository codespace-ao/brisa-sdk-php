<?php

declare(strict_types=1);

namespace Brisa\Tests\Unit;

use Brisa\Http\RawResponse;
use Brisa\Http\Transport;
use Brisa\Http\TransportFailure;

/** Um transporte de mentira: devolve as respostas preparadas, por ordem, e guarda os pedidos. */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $calls = [];

    /** @param list<RawResponse|TransportFailure|callable> $replies */
    public function __construct(private array $replies)
    {
    }

    public static function json(int $status, mixed $data, array $headers = []): RawResponse
    {
        return new RawResponse($status, array_change_key_case($headers) + ['content-type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function problem(int $status, array $problem, array $headers = []): RawResponse
    {
        return new RawResponse($status, array_change_key_case($headers) + ['content-type' => 'application/problem+json'], json_encode($problem + ['status' => $status], JSON_THROW_ON_ERROR));
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): RawResponse
    {
        $call = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $this->calls[] = $call;
        $r = array_shift($this->replies);
        if ($r === null) {
            throw new \LogicException('unexpected call ' . $method . ' ' . $url);
        }
        if ($r instanceof TransportFailure) {
            throw $r;
        }
        return is_callable($r) ? $r($call) : $r;
    }
}
