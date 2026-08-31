<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Mutations\MutationPolicy;
use PHPUnit\Framework\TestCase;

final class MutationPolicyTest extends TestCase
{
    public function testRequiresEnvironmentAndAdministratorSwitch(): void
    {
        $on = static fn () => true;
        $off = static fn () => false;

        self::assertTrue((new MutationPolicy($on, $on))->enabled());
        self::assertFalse((new MutationPolicy($off, $on))->enabled());
        self::assertFalse((new MutationPolicy($on, $off))->enabled());
    }
}
