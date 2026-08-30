<?php

namespace Modules\McpServer\Security;

final class BearerTokenParser
{
    public function parse(?string $authorization): ?string
    {
        if (null === $authorization || !preg_match('/^Bearer ([^\s,]+)$/Di', trim($authorization), $matches)) {
            return null;
        }

        return $matches[1];
    }
}
