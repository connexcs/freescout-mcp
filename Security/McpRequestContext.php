<?php

namespace Modules\McpServer\Security;

final class McpRequestContext
{
    /** @var AuthenticatedPrincipal|null */
    private $principal;

    public function set(AuthenticatedPrincipal $principal): void
    {
        $this->principal = $principal;
    }

    public function clear(): void
    {
        $this->principal = null;
    }

    public function principal(): ?AuthenticatedPrincipal
    {
        return $this->principal;
    }

    /** @return object|null */
    public function user()
    {
        return null === $this->principal ? null : $this->principal->user;
    }

    /** @return object|null */
    public function token()
    {
        return null === $this->principal ? null : $this->principal->token;
    }
}
