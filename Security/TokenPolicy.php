<?php

namespace Modules\McpServer\Security;

final class TokenPolicy
{
    /** @var callable|null */
    private $optionResolver;

    public function __construct(?callable $optionResolver = null)
    {
        $this->optionResolver = $optionResolver;
    }

    public function personalTokensEnabled(): bool
    {
        return $this->booleanOption('personal_tokens_enabled', true);
    }

    public function nonAdminTokensEnabled(): bool
    {
        return $this->booleanOption('allow_non_admin_tokens', true);
    }

    public function lifetimeDays(): int
    {
        return max(0, min(3650, (int) $this->option('token_lifetime_days', 90)));
    }

    /** @param object $user */
    public function canAuthenticateUser($user): bool
    {
        return $this->personalTokensEnabled() && $this->canAuthenticateOAuthUser($user);
    }

    /** @param object $user */
    public function canAuthenticateOAuthUser($user): bool
    {
        if (!method_exists($user, 'isActive') || !$user->isActive()) {
            return false;
        }
        if (method_exists($user, 'isDeleted') && $user->isDeleted()) {
            return false;
        }

        if (isset($user->type) && 1 !== (int) $user->type) {
            return false;
        }

        return $this->nonAdminTokensEnabled()
            || (method_exists($user, 'isAdmin') && $user->isAdmin());
    }

    /** @param object $user */
    public function canIssueForUser($user): bool
    {
        return $this->canAuthenticateUser($user);
    }

    /** @return mixed */
    private function option(string $name, $default)
    {
        if (null !== $this->optionResolver) {
            return ($this->optionResolver)($name, $default);
        }

        return \App\Option::get('mcpserver.'.$name, $default);
    }

    private function booleanOption(string $name, bool $default): bool
    {
        return filter_var($this->option($name, $default), FILTER_VALIDATE_BOOLEAN);
    }
}
