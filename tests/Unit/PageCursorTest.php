<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Support\PageCursor;
use PHPUnit\Framework\TestCase;

final class PageCursorTest extends TestCase
{
    public function testRoundTripsPositiveId(): void
    {
        self::assertSame(42, PageCursor::decode(PageCursor::encode(42)));
        self::assertNull(PageCursor::decode(null));
    }

    /** @dataProvider invalidCursors */
    public function testRejectsMalformedCursor(string $cursor): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PageCursor::decode($cursor);
    }

    public static function invalidCursors(): array
    {
        return [['%%%'], [base64_encode('v1:0')], [base64_encode('v2:3')], [base64_encode('v1:-1')]];
    }
}
