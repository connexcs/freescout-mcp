<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Security\AuthenticatedPrincipal;
use PHPUnit\Framework\TestCase;

final class AuthenticatedPrincipalTest extends TestCase
{
    public function testOAuthScopesAndAuditIdentityAreSeparatedFromPersonalTokens(): void
    {
        $oauth = new AuthenticatedPrincipal((object) ['id' => 7], (object) ['id' => 3], 'oauth', ['mcp:read']);
        $personal = new AuthenticatedPrincipal((object) ['id' => 7], (object) ['id' => 3]);

        self::assertTrue($oauth->hasScope('mcp:read'));
        self::assertFalse($oauth->hasScope('mcp:write'));
        self::assertSame(4294967299, $oauth->auditTokenId());
        self::assertSame(3, $personal->auditTokenId());
    }
}
