<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\OAuth\OAuthCredentialCodec;
use Modules\McpServer\Security\TokenCodec;
use PHPUnit\Framework\TestCase;

final class OAuthCredentialCodecTest extends TestCase
{
    public function testEachCredentialIsOpaqueParseableAndOneWay(): void
    {
        $codec = new OAuthCredentialCodec(new TokenCodec('test-pepper'));

        foreach (['code', 'access', 'refresh'] as $type) {
            $issued = $codec->generate($type);
            $parts = $codec->parse($issued['plain'], $type);

            self::assertNotNull($parts);
            self::assertTrue($codec->verify($parts['secret'], $issued['secret_hash']));
            self::assertStringNotContainsString($parts['secret'], $issued['secret_hash']);
            self::assertNull($codec->parse($issued['plain'], 'code' === $type ? 'access' : 'code'));
        }
    }

    public function testMalformedCredentialsFailClosed(): void
    {
        $codec = new OAuthCredentialCodec(new TokenCodec('test-pepper'));

        self::assertNull($codec->parse('fsmcp_oa_short_secret', 'access'));
        self::assertFalse($codec->verify(str_repeat('a', 43), str_repeat('0', 64)));
    }
}
