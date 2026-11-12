<?php

declare(strict_types=1);

namespace Brisa;

use Brisa\Exception\WebhookSignatureException;

/**
 * Verificação dos webhooks da Brisa (cabeçalho `Brisa-Signature: t=<segundos>,v1=<hex>`, HMAC-SHA256 de `"<t>.<corpo>"` com o segredo do webhook).
 *
 * ```php
 * $event = \Brisa\Webhook::verify(file_get_contents('php://input'), $_SERVER['HTTP_BRISA_SIGNATURE'] ?? null, getenv('BRISA_WEBHOOK_SECRET'));
 * http_response_code(200);
 * ```
 */
final class Webhook
{
    /**
     * Verifica a assinatura e devolve o evento (array).
     *
     * Use o corpo **bruto**, tal como chegou (antes de o interpretar como JSON). Durante a troca do segredo vêm dois `v1`: basta um coincidir; pode
     * também passar os dois segredos (o novo e o anterior).
     *
     * @param string|list<string> $secret
     * @return array<string, mixed>
     *
     * @throws WebhookSignatureException se a assinatura não coincidir ou se `t` estiver fora da tolerância
     */
    public static function verify(string $payload, ?string $signatureHeader, string|array $secret, int $toleranceSeconds = 300, ?int $now = null): array
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            throw new WebhookSignatureException('Falta o cabeçalho Brisa-Signature.');
        }
        [$timestamp, $signatures] = self::parseHeader($signatureHeader);
        if ($timestamp === null || $signatures === []) {
            throw new WebhookSignatureException('Cabeçalho Brisa-Signature mal formado.');
        }
        $now ??= time();
        if ($toleranceSeconds > 0 && abs($now - $timestamp) > $toleranceSeconds) {
            throw new WebhookSignatureException(sprintf('A assinatura tem %d s de diferença do relógio (máximo %d s).', abs($now - $timestamp), $toleranceSeconds));
        }
        foreach ((array) $secret as $s) {
            $expected = hash_hmac('sha256', $timestamp . '.' . $payload, (string) $s);
            foreach ($signatures as $v) {
                if (hash_equals($expected, $v)) {
                    try {
                        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                    } catch (\JsonException $e) {
                        throw new WebhookSignatureException('A assinatura é válida mas o corpo não é JSON.', 0, $e);
                    }
                    if (!is_array($event)) {
                        throw new WebhookSignatureException('A assinatura é válida mas o corpo não é um objeto JSON.');
                    }
                    return $event;
                }
            }
        }
        throw new WebhookSignatureException('A assinatura não coincide com o segredo do webhook.');
    }

    /** Calcula o cabeçalho `Brisa-Signature` de um corpo: útil para testar o seu endpoint. */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /**
     * `t=<segundos>,v1=<hex>[,v1=<hex>]` → [timestamp, assinaturas].
     *
     * @return array{0: ?int, 1: list<string>}
     */
    public static function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $i = strpos($part, '=');
            if ($i === false) {
                continue;
            }
            $k = trim(substr($part, 0, $i));
            $v = trim(substr($part, $i + 1));
            if ($k === 't' && ctype_digit($v)) {
                $timestamp = (int) $v;
            } elseif ($k === 'v1' && $v !== '' && ctype_xdigit($v)) {
                $signatures[] = strtolower($v);
            }
        }
        return [$timestamp, $signatures];
    }
}
