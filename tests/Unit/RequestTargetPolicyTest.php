<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Security\RequestTargetPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTargetPolicyTest extends TestCase
{
    private function policy(): RequestTargetPolicy
    {
        return new RequestTargetPolicy(
            ['support.example.test', 'localhost:8080', '[::1]:8080'],
            ['https://codex.example.test', 'http://localhost:3000/']
        );
    }

    #[DataProvider('allowedHosts')]
    public function testAllowsOnlyConfiguredRequestHosts(string $host): void
    {
        self::assertTrue($this->policy()->allowsHost($host));
    }

    public static function allowedHosts(): iterable
    {
        yield ['support.example.test'];
        yield ['support.example.test:443'];
        yield ['localhost:8080'];
        yield ['[::1]:8080'];
    }

    #[DataProvider('unsafeHosts')]
    public function testRejectsMalformedAndUnconfiguredHosts(string $host): void
    {
        self::assertFalse($this->policy()->allowsHost($host));
    }

    public static function unsafeHosts(): iterable
    {
        yield [''];
        yield ['evil.example.test'];
        yield ['support.example.test.evil.test'];
        yield ['support.example.test,evil.example.test'];
        yield ['evil@support.example.test'];
        yield ['support.example.test/path'];
        yield ['support.example.test?host=evil.example.test'];
        yield ["support.example.test\r\nX-Forwarded-Host: evil.example.test"];
    }

    public function testOriginMustBeAbsentOrAnExactConfiguredOrigin(): void
    {
        self::assertTrue($this->policy()->allowsOrigin(null));
        self::assertTrue($this->policy()->allowsOrigin('https://codex.example.test'));
        self::assertTrue($this->policy()->allowsOrigin('http://localhost:3000'));
        self::assertFalse($this->policy()->allowsOrigin('https://evil.example.test'));
        self::assertFalse($this->policy()->allowsOrigin('https://codex.example.test.evil.test'));
        self::assertFalse($this->policy()->allowsOrigin('https://user@codex.example.test'));
        self::assertFalse($this->policy()->allowsOrigin('https://codex.example.test/path'));
        self::assertFalse($this->policy()->allowsOrigin("https://codex.example.test\nX-Test: yes"));
    }

    public function testWildcardStillRequiresASyntacticallyValidHttpOrigin(): void
    {
        $policy = new RequestTargetPolicy(['localhost'], ['*']);

        self::assertTrue($policy->allowsOrigin('https://client.example.test'));
        self::assertFalse($policy->allowsOrigin('null'));
        self::assertFalse($policy->allowsOrigin('file://client'));
    }
}
