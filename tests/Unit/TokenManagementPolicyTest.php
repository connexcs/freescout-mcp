<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Security\TokenManagementPolicy;
use Modules\McpServer\Security\TokenPolicy;
use Modules\McpServer\Tests\Support\FakeMcpUser;
use PHPUnit\Framework\TestCase;

final class TokenManagementPolicyTest extends TestCase
{
    public function testUsersCanManageAndIssueOnlyForThemselves(): void
    {
        $management = $this->management();
        $actor = new FakeMcpUser(true, false, 1, 10);
        $self = new FakeMcpUser(true, false, 1, 10);
        $other = new FakeMcpUser(true, false, 1, 20);

        self::assertTrue($management->canManage($actor, $self));
        self::assertTrue($management->canIssue($actor, $self));
        self::assertFalse($management->canManage($actor, $other));
        self::assertFalse($management->canIssue($actor, $other));
    }

    public function testAdminsCanRevokeForOthersButCannotMintForThem(): void
    {
        $management = $this->management();
        $admin = new FakeMcpUser(true, true, 1, 10);
        $other = new FakeMcpUser(true, false, 1, 20);

        self::assertTrue($management->canManage($admin, $other));
        self::assertFalse($management->canIssue($admin, $other));
    }

    public function testDeletedTargetsCannotBeManaged(): void
    {
        $management = $this->management();
        $admin = new FakeMcpUser(true, true, 1, 10);
        $deleted = new FakeMcpUser(false, false, 1, 20, true);

        self::assertFalse($management->canManage($admin, $deleted));
        self::assertFalse($management->canIssue($admin, $deleted));
    }

    private function management(): TokenManagementPolicy
    {
        return new TokenManagementPolicy(new TokenPolicy(static fn ($name, $default) => $default));
    }
}
