<?php

declare(strict_types=1);

namespace Brisa\Tests\Unit;

use Brisa\Exception\WebhookSignatureException;
use Brisa\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const BODY = '{"id":"evt_1","type":"document.issued","data":{"id":7,"number":"FT 2026/7"}}';

    public function testAValidSignatureGivesTheEvent(): void
    {
        $header = Webhook::sign(self::BODY, 'whsec_a', 1_700_000_000);
        $event = Webhook::verify(self::BODY, $header, 'whsec_a', 300, 1_700_000_100);
        self::assertSame('document.issued', $event['type']);
        self::assertSame(7, $event['data']['id']);
    }

    public function testMatchesTheReferenceHmac(): void
    {
        // HMAC-SHA256("1700000000.{}", "segredo"), as the API computes it
        self::assertSame('t=1700000000,v1=' . hash_hmac('sha256', '1700000000.{}', 'segredo'), Webhook::sign('{}', 'segredo', 1_700_000_000));
    }

    public function testDuringSecretRotationEitherSignatureOrSecretWorks(): void
    {
        $old = Webhook::sign(self::BODY, 'whsec_old', 1_700_000_000);
        $new = Webhook::sign(self::BODY, 'whsec_new', 1_700_000_000);
        $both = $old . ',v1=' . substr($new, strpos($new, 'v1=') + 3);
        self::assertSame('evt_1', Webhook::verify(self::BODY, $both, 'whsec_new', 300, 1_700_000_000)['id']);
        self::assertSame('evt_1', Webhook::verify(self::BODY, $old, ['whsec_new', 'whsec_old'], 300, 1_700_000_000)['id']);
    }

    public function testRejectsAnotherSecret(): void
    {
        $this->expectException(WebhookSignatureException::class);
        Webhook::verify(self::BODY, Webhook::sign(self::BODY, 'whsec_a', 1_700_000_000), 'whsec_b', 300, 1_700_000_000);
    }

    public function testRejectsAChangedBody(): void
    {
        $this->expectException(WebhookSignatureException::class);
        Webhook::verify(str_replace('7', '8', self::BODY), Webhook::sign(self::BODY, 'whsec_a', 1_700_000_000), 'whsec_a', 300, 1_700_000_000);
    }

    public function testRejectsAnOldSignature(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/601 s/');
        Webhook::verify(self::BODY, Webhook::sign(self::BODY, 'whsec_a', 1_700_000_000), 'whsec_a', 300, 1_700_000_601);
    }

    public function testRejectsMissingOrMalformedHeaders(): void
    {
        foreach ([null, '', 'v1=abc', 't=abc,v1=zz', 't=1700000000'] as $h) {
            try {
                Webhook::verify(self::BODY, $h, 'whsec_a', 0);
                self::fail('expected an exception for ' . var_export($h, true));
            } catch (WebhookSignatureException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
