<?php

namespace Modules\McpServer\Security;

final class AuthenticatedPrincipal
{
    /** @var object */
    public $user;

    /** @var object */
    public $token;
    /** @var string */
    public $credentialType;
    /** @var string[] */
    public $scopes;

    /** @param object $user @param object $token @param string[] $scopes */
    public function __construct($user, $token, string $credentialType = 'personal', array $scopes = ['mcp:read', 'mcp:write'])
    {
        $this->user = $user;
        $this->token = $token;
        $this->credentialType = $credentialType;
        $this->scopes = $scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function auditTokenId(): int
    {
        $id = (int) $this->token->id;

        return 'oauth' === $this->credentialType ? 4294967296 + $id : $id;
    }
}
