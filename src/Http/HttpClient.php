<?php

declare(strict_types=1);

namespace Brisa\Http;

use Brisa\ApiResponse;
use Brisa\Brisa;
use Brisa\Exception\ApiException;
use Brisa\Exception\BrisaException;
use Brisa\Exception\ConnectionException;
use Brisa\Exception\TimeoutException;

/**
 * Autenticação, repetições, `Idempotency-Key` e erros: o que todos os pedidos do SDK partilham.
 *
 * @internal use {@see Brisa}
 */
final class HttpClient
{
    public const DEFAULT_BASE_URL = 'https://api.brisa.ao';
    private const RETRYABLE = [429, 502, 503];

    public readonly string $baseUrl;
    private readonly string $apiKey;
    private readonly float $timeout;
    private readonly int $maxRetries;
    private readonly float $maxRetryDelay;
    /** @var array<string, string> */
    private readonly array $headers;
    private readonly Transport $transport;
    /** @var callable(float): void */
    private $sleep;
    /** @var array{limit: int, remaining: int, reset: int}|null */
    public ?array $lastRateLimit = null;

    /** @param array<string, mixed> $options ver {@see Brisa::__construct()} */
    public function __construct(array $options = [])
    {
        $apiKey = $options['apiKey'] ?? self::env('BRISA_API_KEY');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new BrisaException("Falta a chave de API: passe ['apiKey' => ...] ou defina BRISA_API_KEY.");
        }
        $this->apiKey = $apiKey;
        $base = $options['baseUrl'] ?? self::env('BRISA_API_URL') ?? self::DEFAULT_BASE_URL;
        $this->baseUrl = rtrim((string) $base, '/');
        $this->timeout = (float) ($options['timeout'] ?? 30.0);
        $this->maxRetries = (int) ($options['maxRetries'] ?? 2);
        $this->maxRetryDelay = (float) ($options['maxRetryDelay'] ?? 60.0);
        $this->headers = (array) ($options['headers'] ?? []);
        $this->transport = $options['transport'] ?? new CurlTransport();
        $this->sleep = $options['sleep'] ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /** Gera um `Idempotency-Key` aleatório (`brisa-php-<32 hex>`). */
    public static function idempotencyKey(): string
    {
        return 'brisa-php-' . bin2hex(random_bytes(16));
    }

    /** Segundos de um `Retry-After` (número ou data HTTP). */
    public static function parseRetryAfter(?string $value, ?int $now = null): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0, (int) ceil((float) $value));
        }
        $t = strtotime($value);
        return $t === false ? null : max(0, $t - ($now ?? time()));
    }

    /** @param array<string, mixed>|null $query */
    public function url(string $path, ?array $query = null): string
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $parts = [];
            foreach ($query as $k => $v) {
                if ($v === null) {
                    continue;
                }
                foreach (is_array($v) ? $v : [$v] as $x) {
                    $parts[] = rawurlencode((string) $k) . '=' . rawurlencode(is_bool($x) ? ($x ? 'true' : 'false') : (string) $x);
                }
            }
            if ($parts) {
                $url .= (str_contains($url, '?') ? '&' : '?') . implode('&', $parts);
            }
        }
        return $url;
    }

    /**
     * @param array{query?: array<string, mixed>|null, body?: mixed, as?: 'json'|'text'|'bytes', accept?: string} $request
     * @param array{idempotencyKey?: string, timeout?: float, maxRetries?: int, headers?: array<string, string>} $options
     */
    public function request(string $method, string $path, array $request = [], array $options = []): ApiResponse
    {
        $url = $this->url($path, $request['query'] ?? null);
        $headers = array_merge(
            [
                'Accept' => $request['accept'] ?? 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
                'User-Agent' => 'brisa-sdk-php/' . Brisa::VERSION . ' php/' . PHP_VERSION,
            ],
            $this->headers,
            $options['headers'] ?? [],
        );
        if (isset($options['idempotencyKey'])) {
            $headers['Idempotency-Key'] = $options['idempotencyKey'];
        }
        $body = null;
        if (array_key_exists('body', $request)) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($request['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        // a POST is repeated after a network failure only with an Idempotency-Key: otherwise it could be done twice
        $safeToRepeat = $method !== 'POST' || isset($options['idempotencyKey']);
        $maxRetries = $options['maxRetries'] ?? $this->maxRetries;
        $timeout = $options['timeout'] ?? $this->timeout;
        $as = $request['as'] ?? 'json';

        for ($attempt = 0; ; $attempt++) {
            try {
                $res = $this->transport->send($method, $url, $headers, $body, $timeout);
            } catch (TransportFailure $e) {
                $error = $e->timedOut
                    ? new TimeoutException(sprintf('A API não respondeu em %s s (%s %s).', $timeout, $method, $path), 0, $e)
                    : new ConnectionException(sprintf('Não foi possível falar com a API (%s %s): %s', $method, $path, $e->getMessage()), 0, $e);
                if ($safeToRepeat && $attempt < $maxRetries) {
                    ($this->sleep)(self::backoff($attempt));
                    continue;
                }
                throw $error;
            }
            $rate = self::rateLimitOf($res);
            if ($rate !== null) {
                $this->lastRateLimit = $rate;
            }
            if ($res->status >= 200 && $res->status < 300) {
                return new ApiResponse(self::read($res, $as), $res->status, $res->headers, $res->header('idempotent-replayed') === 'true', $rate);
            }
            $retryAfter = self::parseRetryAfter($res->header('retry-after'));
            if (in_array($res->status, self::RETRYABLE, true) && $attempt < $maxRetries) {
                $wait = $retryAfter ?? self::backoff($attempt);
                if ($wait <= $this->maxRetryDelay) {
                    ($this->sleep)((float) $wait);
                    continue;
                }
            }
            throw ApiException::from(self::problemOf($res, $path), $res->headers, $retryAfter);
        }
    }

    /** 0,5 s, 1 s, 2 s... (até 8 s), com variação aleatória. */
    private static function backoff(int $attempt): float
    {
        $base = min(8.0, 0.5 * (2 ** $attempt));
        return $base / 2 + (mt_rand() / mt_getrandmax()) * ($base / 2);
    }

    /** @return array{limit: int, remaining: int, reset: int}|null */
    private static function rateLimitOf(RawResponse $res): ?array
    {
        $limit = $res->header('x-ratelimit-limit');
        if ($limit === null) {
            return null;
        }
        return ['limit' => (int) $limit, 'remaining' => (int) ($res->header('x-ratelimit-remaining') ?? 0), 'reset' => (int) ($res->header('x-ratelimit-reset') ?? 0)];
    }

    private static function read(RawResponse $res, string $as): mixed
    {
        if ($res->status === 204) {
            return null;
        }
        if ($as === 'bytes' || $as === 'text') {
            return $res->body;
        }
        if ($res->body === '') {
            return null;
        }
        try {
            return json_decode($res->body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new BrisaException('A API respondeu com JSON inválido.', 0, $e);
        }
    }

    /** @return array<string, mixed> */
    private static function problemOf(RawResponse $res, string $path): array
    {
        $p = json_decode($res->body, true);
        if (is_array($p) && !array_is_list($p)) {
            $p['status'] = isset($p['status']) && is_int($p['status']) ? $p['status'] : $res->status;
            $p['type'] ??= 'about:blank';
            $p['title'] ??= 'HTTP ' . $res->status;
            return $p;
        }
        // not JSON: a proxy or gateway answered
        $detail = trim(mb_substr($res->body, 0, 500));
        return ['type' => 'about:blank', 'title' => 'HTTP ' . $res->status, 'status' => $res->status, 'detail' => $detail !== '' ? $detail : null, 'instance' => $path];
    }

    private static function env(string $name): ?string
    {
        $v = getenv($name);
        return $v === false || $v === '' ? null : $v;
    }
}
