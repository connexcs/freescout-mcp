<?php

namespace Modules\McpServer\Mutations;

final class MutationPolicy
{
    /** @var callable|null */
    private $optionResolver;
    /** @var callable|null */
    private $configResolver;

    public function __construct(?callable $optionResolver = null, ?callable $configResolver = null)
    {
        $this->optionResolver = $optionResolver;
        $this->configResolver = $configResolver;
    }

    public function enabled(): bool
    {
        $configured = null === $this->configResolver
            ? config('mcpserver.mutations_enabled', false)
            : ($this->configResolver)('mcpserver.mutations_enabled', false);
        if (!filter_var($configured, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $value = null === $this->optionResolver
            ? \App\Option::get('mcpserver.mutations_enabled', false)
            : ($this->optionResolver)('mcpserver.mutations_enabled', false);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
