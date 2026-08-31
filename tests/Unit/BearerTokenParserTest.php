<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Security\BearerTokenParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BearerTokenParserTest extends TestCase
{
    public function testParsesOneBearerCredential(): void
    {
        self::assertSame('fsmcp_token', (new BearerTokenParser())->parse('Bearer fsmcp_token'));
        self::assertSame('fsmcp_token', (new BearerTokenParser())->parse('bearer fsmcp_token'));
    }

    #[DataProvider('invalidHeaders')]
    public function testRejectsAmbiguousOrMalformedHeaders(?string $header): void
    {
        self::assertNull((new BearerTokenParser())->parse($header));
    }

    /** @return iterable<string, array{string|null}> */
    public static function invalidHeaders(): iterable
    {
        yield 'missing' => [null];
        yield 'basic' => ['Basic abc'];
        yield 'empty' => ['Bearer '];
        yield 'multiple' => ['Bearer one, Bearer two'];
        yield 'embedded whitespace' => ['Bearer one two'];
        yield 'tab separator' => ["Bearer\tone"];
        yield 'line feed injection' => ["Bearer one\nX-Injected: yes"];
        yield 'carriage return injection' => ["Bearer one\rX-Injected: yes"];
    }
}
