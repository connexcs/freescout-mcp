<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Security\TokenCodec;
use PHPUnit\Framework\TestCase;

final class TokenCodecTest extends TestCase
{
    public function testGeneratedTokenIsHighEntropyParseableAndVerifiable(): void
    {
        $codec = new TokenCodec('base64:'.base64_encode(str_repeat('k', 32)));
        $first = $codec->generate();
        $second = $codec->generate();

        self::assertNotSame($first['token'], $second['token']);
        self::assertMatchesRegularExpression('/^fsmcp_[A-Za-z0-9_-]{16}_[A-Za-z0-9_-]{43}$/', $first['token']);

        $parts = $codec->parse($first['token']);
        self::assertNotNull($parts);
        self::assertSame($first['selector'], $parts['selector']);
        self::assertTrue($codec->verify($parts['secret'], $first['secret_hash']));
        self::assertStringNotContainsString($parts['secret'], $first['secret_hash']);
    }

    public function testWrongPepperAndMalformedTokensFail(): void
    {
        $issuer = new TokenCodec('first-pepper');
        $verifier = new TokenCodec('second-pepper');
        $material = $issuer->generate();
        $parts = $verifier->parse($material['token']);

        self::assertNotNull($parts);
        self::assertFalse($verifier->verify($parts['secret'], $material['secret_hash']));
        self::assertNull($verifier->parse('fsmcp_bad'));
        self::assertNull($verifier->parse("fsmcp_aaaaaaaaaaaaaaaa_".str_repeat('b', 42)."\n"));
    }

    public function testFingerprintsAreKeyedAndStable(): void
    {
        $one = new TokenCodec('pepper-one');
        $two = new TokenCodec('pepper-two');

        self::assertSame($one->fingerprint('value'), $one->fingerprint('value'));
        self::assertNotSame($one->fingerprint('value'), $two->fingerprint('value'));
    }
}
