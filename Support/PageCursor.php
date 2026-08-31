<?php

namespace Modules\McpServer\Support;

final class PageCursor
{
    public static function encode(int $id): string
    {
        return rtrim(strtr(base64_encode('v1:'.$id), '+/', '-_'), '=');
    }

    public static function decode(?string $cursor): ?int
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if (false === $decoded || 1 !== preg_match('/\Av1:([1-9][0-9]*)\z/', $decoded, $matches)) {
            throw new \InvalidArgumentException('Invalid pagination cursor.');
        }

        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $id) {
            throw new \InvalidArgumentException('Invalid pagination cursor.');
        }

        return (int) $id;
    }
}
