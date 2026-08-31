<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\OAuth\OAuthCredentialCodec;
use Modules\McpServer\OAuth\OAuthException;
use Modules\McpServer\OAuth\OAuthTokenService;
use Modules\McpServer\Security\TokenCodec;
use Modules\McpServer\Security\TokenPolicy;
use PHPUnit\Framework\TestCase;

final class OAuthTokenServiceTest extends TestCase
{
    public function testScopesAlwaysIncludeReadAndMayRequestOfflineAndWrite(): void
    {
        $service = $this->service();

        self::assertSame(['mcp:read'], $service->validateScopes(null));
        self::assertSame(['mcp:read', 'offline_access'], $service->validateScopes('offline_access'));
        self::assertSame(['mcp:read', 'mcp:write'], $service->validateScopes('mcp:write'));
    }

    public function testUnknownScopeFailsClosed(): void
    {
        $this->expectException(OAuthException::class);
        $this->service()->validateScopes('mcp:read admin');
    }

    private function service(): OAuthTokenService
    {
        return new OAuthTokenService(
            new OAuthCredentialCodec(new TokenCodec('test-pepper')),
            new TokenPolicy(static fn ($name, $default) => $default)
        );
    }
}
