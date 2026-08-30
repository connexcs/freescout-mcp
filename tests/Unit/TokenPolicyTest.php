<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Security\TokenPolicy;
use Modules\McpServer\Tests\Support\FakeMcpUser;
use PHPUnit\Framework\TestCase;

final class TokenPolicyTest extends TestCase
{
    public function testActiveRegularUsersAreAllowedByDefault(): void
    {
        $policy = new TokenPolicy(static fn ($name, $default) => $default);

        self::assertTrue($policy->canAuthenticateUser(new FakeMcpUser(true, false)));
        self::assertSame(90, $policy->lifetimeDays());
    }

    public function testPolicyCanRestrictAuthenticationToAdmins(): void
    {
        $policy = new TokenPolicy(static function ($name, $default) {
            return 'allow_non_admin_tokens' === $name ? false : $default;
        });

        self::assertFalse($policy->canAuthenticateUser(new FakeMcpUser(true, false)));
        self::assertTrue($policy->canAuthenticateUser(new FakeMcpUser(true, true)));
    }

    public function testDisabledUsersRobotsAndGlobalDisableFailClosed(): void
    {
        $defaultPolicy = new TokenPolicy(static fn ($name, $default) => $default);
        $disabledPolicy = new TokenPolicy(static function ($name, $default) {
            return 'personal_tokens_enabled' === $name ? false : $default;
        });

        self::assertFalse($defaultPolicy->canAuthenticateUser(new FakeMcpUser(false, true)));
        self::assertFalse($defaultPolicy->canAuthenticateUser(new FakeMcpUser(true, true, 2)));
        self::assertFalse($disabledPolicy->canAuthenticateUser(new FakeMcpUser(true, true)));
    }

    public function testLifetimeIsBounded(): void
    {
        self::assertSame(0, (new TokenPolicy(static fn () => -5))->lifetimeDays());
        self::assertSame(3650, (new TokenPolicy(static fn () => 99999))->lifetimeDays());
    }
}
