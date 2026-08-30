<?php

namespace Modules\McpServer\Contracts;

interface TokenRepository
{
    /** @return object|null */
    public function findBySelector(string $selector);

    /** @param object $token */
    public function markUsed($token, ?string $ipAddress): void;
}
