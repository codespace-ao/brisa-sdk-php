<?php

declare(strict_types=1);

namespace Brisa\Http;

/**
 * O transporte por omissão, sobre a extensão cURL. Verifica sempre o certificado TLS: com `caBundle` usa esse ficheiro de CAs; sem ele e sem
 * `curl.cainfo`/`openssl.cafile` no php.ini (o caso habitual do PHP no Windows), usa o armazém de certificados do sistema.
 */
final class CurlTransport implements Transport
{
    public function __construct(private readonly ?string $caBundle = null)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): RawResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new TransportFailure('Não foi possível iniciar o cURL.');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $received = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT_MS => (int) max(1, round($timeoutSeconds * 1000)),
            CURLOPT_CONNECTTIMEOUT_MS => (int) max(1, round(min($timeoutSeconds, 10) * 1000)),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HEADERFUNCTION => static function ($_, string $line) use (&$received): int {
                $i = strpos($line, ':');
                if ($i !== false) {
                    $received[strtolower(trim(substr($line, 0, $i)))] = trim(substr($line, $i + 1));
                } elseif (str_starts_with($line, 'HTTP/')) {
                    $received = []; // a new response (after a 100 Continue): only its headers count
                }
                return strlen($line);
            },
        ]);
        if ($this->caBundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->caBundle);
        } elseif (!ini_get('curl.cainfo') && !ini_get('openssl.cafile') && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        if ($out === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new TransportFailure($error !== '' ? $error : 'falha de rede', $errno === CURLE_OPERATION_TIMEDOUT);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return new RawResponse($status, $received, (string) $out);
    }
}
