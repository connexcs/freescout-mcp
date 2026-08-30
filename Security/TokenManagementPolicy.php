<?php

namespace Modules\McpServer\Security;

final class TokenManagementPolicy
{
    /** @var TokenPolicy */
    private $tokens;

    public function __construct(TokenPolicy $tokens)
    {
        $this->tokens = $tokens;
    }

    /** @param object $actor @param object $target */
    public function canManage($actor, $target): bool
    {
        if (method_exists($target, 'isDeleted') && $target->isDeleted()) {
            return false;
        }

        return $actor->id == $target->id
            || (method_exists($actor, 'isAdmin') && $actor->isAdmin());
    }

    /** @param object $actor @param object $target */
    public function canIssue($actor, $target): bool
    {
        return $actor->id == $target->id
            && $this->canManage($actor, $target)
            && $this->tokens->canIssueForUser($target);
    }
}
