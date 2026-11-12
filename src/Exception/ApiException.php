<?php

declare(strict_types=1);

namespace Brisa\Exception;

/**
 * A API respondeu com um erro (RFC 9457, `application/problem+json`).
 * Programe contra `status`, `type` e `field`, não contra o texto de `detail` (em português, pode variar).
 */
class ApiException extends BrisaException
{
    /**
     * @param array<string, mixed>  $problem  o documento de erro completo, incluindo membros que o SDK não conhece
     * @param array<string, string> $headers  cabeçalhos da resposta (nomes em minúsculas)
     * @param int|null              $retryAfter segundos a esperar (`Retry-After`), quando a API os indicou
     */
    public function __construct(
        public readonly array $problem,
        public readonly array $headers = [],
        public readonly ?int $retryAfter = null,
    ) {
        $detail = isset($problem['detail']) && is_string($problem['detail']) ? ': ' . $problem['detail'] : '';
        parent::__construct($this->status() . ' ' . $this->title() . $detail, $this->status());
    }

    /** O estado HTTP. */
    public function status(): int
    {
        return (int) ($this->problem['status'] ?? 0);
    }

    /** `urn:brisa:...`: identificador estável do tipo de problema. */
    public function type(): string
    {
        return (string) ($this->problem['type'] ?? 'about:blank');
    }

    public function title(): string
    {
        return (string) ($this->problem['title'] ?? '');
    }

    public function detail(): ?string
    {
        return isset($this->problem['detail']) ? (string) $this->problem['detail'] : null;
    }

    public function instance(): ?string
    {
        return isset($this->problem['instance']) ? (string) $this->problem['instance'] : null;
    }

    /** O campo que causou o erro (regras fiscais, `422`). */
    public function field(): ?string
    {
        return isset($this->problem['field']) ? (string) $this->problem['field'] : null;
    }

    /**
     * Erros de validação (`400`): campo => mensagem.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        $e = $this->problem['errors'] ?? [];
        if (!is_array($e)) {
            return [];
        }
        $out = [];
        foreach ($e as $k => $v) {
            $out[(string) $k] = is_array($v) ? implode('; ', array_map('strval', $v)) : (string) $v;
        }
        return $out;
    }

    /** `true` para `429`, `502` e `503`: repetir mais tarde (com o mesmo `Idempotency-Key`). */
    public function isRetryable(): bool
    {
        return in_array($this->status(), [429, 502, 503], true);
    }

    /**
     * A exceção certa para o estado do problema.
     *
     * @param array<string, mixed>  $problem
     * @param array<string, string> $headers
     */
    public static function from(array $problem, array $headers = [], ?int $retryAfter = null): self
    {
        $s = (int) ($problem['status'] ?? 0);
        return match (true) {
            $s === 400 => new ValidationException($problem, $headers, $retryAfter),
            $s === 401 => new AuthenticationException($problem, $headers, $retryAfter),
            $s === 402 => new PlanException($problem, $headers, $retryAfter),
            $s === 403 => new PermissionException($problem, $headers, $retryAfter),
            $s === 404 => new NotFoundException($problem, $headers, $retryAfter),
            $s === 409 => new ConflictException($problem, $headers, $retryAfter),
            $s === 422 => new RuleException($problem, $headers, $retryAfter),
            $s === 429 => new RateLimitException($problem, $headers, $retryAfter),
            $s >= 500 => new ServerException($problem, $headers, $retryAfter),
            default => new self($problem, $headers, $retryAfter),
        };
    }
}
